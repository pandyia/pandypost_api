<?php

namespace App\Services\Billing\Tenant;

use App\Enums\SubscriptionChangeKind;
use App\Enums\SubscriptionChangeStatus;
use App\Exceptions\BillingException;
use App\Models\Price;
use App\Models\Subscription;
use App\Models\SubscriptionChange;
use App\Models\Workspace;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

/**
 * Troca de plano/frequência da assinatura do workspace (spec 024).
 *
 * - Upgrade (e qualquer troca em trial): `subscriptions.update` direto no Stripe com `pending_if_incomplete`;
 *   se a cobrança falhar ou pedir 3DS, o Stripe não aplica a troca e a assinatura segue no preço antigo.
 * - Downgrade: Subscription Schedule de 2 fases (preço atual até o fim do ciclo, depois o novo).
 *
 * O Stripe é a fonte de verdade; a assinatura local é sincronizada aqui (idempotente com o webhook do Cashier).
 */
class ChangeSubscriptionService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function change(Workspace $workspace, string $priceUuid): Subscription
    {
        // Preço inexistente, inativo ou de plano inativo responde 404 (resolve antes de qualquer regra).
        $target = Price::active()
            ->whereHas('plan', fn ($query) => $query->active())
            ->with('plan')
            ->where('uuid', $priceUuid)
            ->firstOrFail();

        $failure = null;
        $notify = null;

        $subscription = DB::transaction(function () use ($workspace, $target, &$failure, &$notify) {
            // Lock: duas trocas simultâneas se enfileiram e a segunda enxerga o estado já alterado.
            $subscription = $this->lockedSubscription($workspace);

            if (! $subscription) {
                throw BillingException::noActiveSubscription();
            }

            $onTrial = $this->assertChangeable($subscription);
            $current = $this->currentPrice($subscription);

            if (! $target->gateway_price_id) {
                throw BillingException::priceNotSynced();
            }

            if ($target->gateway_price_id === $subscription->stripe_price || $target->id === $current->id) {
                throw BillingException::samePrice();
            }

            if ($target->currency !== $current->currency) {
                throw BillingException::priceCurrencyMismatch();
            }

            $pending = $this->pendingOf($subscription);

            // Mesmo downgrade já agendado: nada a fazer (duplo clique).
            if ($pending && $pending->price_id === $target->id && SubscriptionChangeKind::for($current, $target, $onTrial) === SubscriptionChangeKind::SCHEDULED) {
                return $subscription;
            }

            try {
                if (SubscriptionChangeKind::for($current, $target, $onTrial) === SubscriptionChangeKind::IMMEDIATE) {
                    $notify = $this->applyNow($subscription, $pending, $target, $onTrial);
                } else {
                    $notify = $this->schedule($subscription, $pending, $current, $target);
                }
            } catch (BillingException $e) {
                // Não relança dentro da transação: o cancelamento do agendamento anterior (já feito no
                // Stripe) precisa ser gravado mesmo quando a troca falha.
                $failure = $e;
            }

            return $subscription;
        });

        if ($failure) {
            throw $failure;
        }

        if ($notify) {
            $this->notifications->notifySubscriptionChanged($workspace, $notify['event'], $notify['resource'], $notify['plan_name'], $notify['effective_at'] ?? null);
        }

        return $this->fresh($subscription);
    }

    /**
     * Desfaz o downgrade agendado (libera o schedule no Stripe).
     */
    public function cancelPending(Workspace $workspace): Subscription
    {
        $subscription = DB::transaction(function () use ($workspace) {
            $subscription = $this->lockedSubscription($workspace);
            $pending = $subscription ? $this->pendingOf($subscription) : null;

            if (! $subscription || ! $pending) {
                throw BillingException::noPendingChange();
            }

            $this->releaseSchedule($pending);

            return $subscription;
        });

        return $this->fresh($subscription);
    }

    // ---------------------------------------------------------------
    // Fluxos
    // ---------------------------------------------------------------

    /** @return array{event: string, resource: string, plan_name: string} */
    private function applyNow(Subscription $subscription, ?SubscriptionChange $pending, Price $target, bool $onTrial): array
    {
        // Assinatura presa a um schedule não aceita troca direta: libera o agendamento antes (RF-11).
        if ($pending) {
            $this->releaseSchedule($pending);
        }

        try {
            $stripe = $this->stripe();
            $remote = $stripe->subscriptions->retrieve($subscription->stripe_id);
            $item = $this->itemOf($remote->toArray(), $subscription->stripe_price);

            $params = [
                'items' => [['id' => $item['id'], 'price' => $target->gateway_price_id]],
                'proration_behavior' => 'always_invoice',
                'payment_behavior' => 'pending_if_incomplete',
                'expand' => ['latest_invoice'],
            ];

            if ($onTrial) {
                $params['trial_end'] = 'now';
            }

            $updated = $stripe->subscriptions->update($subscription->stripe_id, $params, [
                'idempotency_key' => 'change:'.$subscription->stripe_id.':'.$target->uuid.':'.now()->format('YmdHi'),
            ]);
        } catch (CardException $e) {
            report($e);

            throw BillingException::paymentFailed();
        } catch (ApiErrorException $e) {
            report($e);

            throw BillingException::gatewayError();
        }

        $data = $updated->toArray();

        // O Stripe não aplicou a troca: cobrança pendente (3DS ou cartão recusado).
        if (! empty($data['pending_update'])) {
            $this->throwPendingPayment($data);
        }

        $this->syncLocal($subscription, $data);

        return [
            'event' => 'upgrade',
            'resource' => $subscription->stripe_id.':'.$target->uuid.':'.now()->timestamp,
            'plan_name' => (string) $target->plan?->name,
        ];
    }

    /** @return array{event: string, resource: string, plan_name: string, effective_at: Carbon} */
    private function schedule(Subscription $subscription, ?SubscriptionChange $pending, Price $current, Price $target): array
    {
        // Substitui o agendamento anterior (RF-9).
        if ($pending) {
            $this->releaseSchedule($pending);
        }

        $stripe = $this->stripe();
        $scheduleId = null;

        try {
            $remote = $stripe->subscriptions->retrieve($subscription->stripe_id)->toArray();
            $periodEnd = $this->periodEnd($remote);
            $quantity = $this->itemOf($remote, $subscription->stripe_price)['quantity'] ?? 1;

            $schedule = $stripe->subscriptionSchedules->create(['from_subscription' => $subscription->stripe_id])->toArray();
            $scheduleId = $schedule['id'];

            $firstPhase = $schedule['phases'][0] ?? [];

            $stripe->subscriptionSchedules->update($scheduleId, [
                'end_behavior' => 'release',
                'proration_behavior' => 'none',
                'phases' => [
                    array_filter([
                        'start_date' => $firstPhase['start_date'] ?? null,
                        'end_date' => $periodEnd,
                        'items' => [['price' => $subscription->stripe_price, 'quantity' => $quantity]],
                    ], fn ($v) => $v !== null),
                    [
                        'items' => [['price' => $target->gateway_price_id, 'quantity' => $quantity]],
                        'iterations' => 1,
                    ],
                ],
            ]);
        } catch (ApiErrorException $e) {
            report($e);

            // Não deixa um schedule órfão no Stripe.
            if ($scheduleId) {
                try {
                    $stripe->subscriptionSchedules->release($scheduleId);
                } catch (ApiErrorException $releaseError) {
                    report($releaseError);
                }
            }

            throw BillingException::gatewayError();
        }

        $effectiveAt = Carbon::createFromTimestamp($periodEnd);

        SubscriptionChange::create([
            'subscription_id' => $subscription->id,
            'price_id' => $target->id,
            'stripe_schedule_id' => $scheduleId,
            'effective_at' => $effectiveAt,
            'status' => SubscriptionChangeStatus::PENDING,
        ]);

        return [
            'event' => 'scheduled',
            'resource' => $scheduleId,
            'plan_name' => (string) $target->plan?->name,
            'effective_at' => $effectiveAt,
        ];
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------

    private function stripe(): StripeClient
    {
        return Cashier::stripe();
    }

    private function lockedSubscription(Workspace $workspace): ?Subscription
    {
        return Subscription::where('workspace_id', $workspace->id)
            ->where('type', 'default')
            ->lockForUpdate()
            ->first();
    }

    private function pendingOf(Subscription $subscription): ?SubscriptionChange
    {
        return SubscriptionChange::where('subscription_id', $subscription->id)->pending()->first();
    }

    /**
     * Só `active` e `trialing` sem cancelamento agendado trocam (RN-3). Devolve se está em trial.
     */
    private function assertChangeable(Subscription $subscription): bool
    {
        if (! in_array($subscription->stripe_status, ['active', 'trialing'], true) || $subscription->ends_at !== null) {
            throw BillingException::subscriptionChangeNotAllowed();
        }

        return $subscription->stripe_status === 'trialing';
    }

    private function currentPrice(Subscription $subscription): Price
    {
        // Assinatura sem preço único (ex.: vários itens): não há como classificar a troca.
        if (! $subscription->stripe_price) {
            throw BillingException::subscriptionChangeNotAllowed();
        }

        $price = Price::withTrashed()->where('gateway_price_id', $subscription->stripe_price)->first();

        // Preço da assinatura que não existe no catálogo local: não dá para classificar a troca.
        if (! $price) {
            throw BillingException::subscriptionChangeNotAllowed();
        }

        return $price;
    }

    /**
     * Libera o schedule no Stripe e marca a linha como cancelada. Schedule já inexistente no
     * Stripe (404) conta como liberado.
     */
    private function releaseSchedule(SubscriptionChange $change): void
    {
        try {
            $this->stripe()->subscriptionSchedules->release($change->stripe_schedule_id);
        } catch (ApiErrorException $e) {
            if ($e->getHttpStatus() !== 404) {
                report($e);

                throw BillingException::gatewayError();
            }
        }

        $change->update(['status' => SubscriptionChangeStatus::CANCELED]);
    }

    private function throwPendingPayment(array $data): never
    {
        $invoice = $data['latest_invoice'] ?? null;

        if (is_string($invoice)) {
            try {
                $invoice = $this->stripe()->invoices->retrieve($invoice)->toArray();
            } catch (ApiErrorException $e) {
                report($e);
                $invoice = null;
            }
        }

        $intent = is_array($invoice) ? ($invoice['payment_intent'] ?? null) : null;

        if (is_string($intent)) {
            try {
                $intent = $this->stripe()->paymentIntents->retrieve($intent)->toArray();
            } catch (ApiErrorException $e) {
                report($e);
                $intent = null;
            }
        }

        if (is_array($intent) && ($intent['status'] ?? null) === 'requires_payment_method') {
            throw BillingException::paymentFailed();
        }

        throw BillingException::paymentActionRequired(is_array($invoice) ? ($invoice['hosted_invoice_url'] ?? null) : null);
    }

    /** Escolhe o item da assinatura no preço atual (ou o primeiro). */
    private function itemOf(array $remote, ?string $priceId): array
    {
        $items = $remote['items']['data'] ?? [];

        foreach ($items as $item) {
            if (($item['price']['id'] ?? null) === $priceId) {
                return $item;
            }
        }

        return $items[0] ?? throw BillingException::gatewayError();
    }

    private function periodEnd(array $remote): int
    {
        return (int) ($remote['current_period_end'] ?? $remote['items']['data'][0]['current_period_end'] ?? throw BillingException::gatewayError());
    }

    /**
     * Espelha a assinatura devolvida pelo Stripe na linha local (como o ConfirmCheckoutService).
     */
    private function syncLocal(Subscription $subscription, array $data): void
    {
        $items = $data['items']['data'] ?? [];
        $single = count($items) === 1;
        $trialEnd = isset($data['trial_end']) ? Carbon::createFromTimestamp($data['trial_end']) : null;

        $subscription->forceFill([
            'stripe_status' => $data['status'] ?? $subscription->stripe_status,
            'stripe_price' => $single ? $items[0]['price']['id'] : null,
            'quantity' => $single ? ($items[0]['quantity'] ?? null) : null,
            'trial_ends_at' => $trialEnd && $trialEnd->isFuture() ? $trialEnd : null,
        ])->save();

        foreach ($items as $item) {
            $subscription->items()->updateOrCreate(['stripe_id' => $item['id']], [
                'stripe_product' => $item['price']['product'],
                'stripe_price' => $item['price']['id'],
                'quantity' => $item['quantity'] ?? null,
            ]);
        }

        $subscription->items()->whereNotIn('stripe_id', array_column($items, 'id'))->delete();
    }

    private function fresh(Subscription $subscription): Subscription
    {
        // O fim do período pode ter mudado com a troca (ex.: mensal→anual): descarta a data em cache
        Cache::forget($subscription->nextBillingCacheKey());

        return $subscription->refresh()->unsetRelation('pendingChange');
    }
}
