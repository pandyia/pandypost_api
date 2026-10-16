<?php

namespace App\Services\Billing\Tenant;

use App\Exceptions\BillingException;
use App\Models\Subscription;
use App\Models\Workspace;
use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

/**
 * Confirma uma Checkout Session direto no Stripe e sincroniza a assinatura local,
 * sem depender da chegada do webhook. Espelha o handler `customer.subscription.created`
 * do Cashier; o webhook segue como fonte de verdade e atualiza o estado depois.
 */
class ConfirmCheckoutService
{
    public function confirm(Workspace $workspace, string $sessionId): Subscription
    {
        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId, [
                'expand' => ['subscription'],
            ]);
        } catch (ApiErrorException $e) {
            report($e);

            // Sessão inexistente no Stripe equivale a "não é deste workspace"
            if ($e->getHttpStatus() === 404) {
                throw BillingException::checkoutSessionNotFound();
            }

            throw BillingException::gatewayError();
        }

        // Posse: só aceita sessões do customer deste workspace
        if (! $workspace->stripe_id || ($session->customer ?? null) !== $workspace->stripe_id) {
            throw BillingException::checkoutSessionNotFound();
        }

        $stripeSubscription = $session->subscription ?? null;

        if (($session->status ?? null) !== 'complete' || ! is_object($stripeSubscription)) {
            throw BillingException::noActiveSubscription();
        }

        return $this->sync($workspace, $stripeSubscription->toArray());
    }

    private function sync(Workspace $workspace, array $data): Subscription
    {
        // Idempotência: se o webhook (ou uma confirmação anterior) já criou, não sobrescreve o estado
        $existing = $workspace->subscriptions()->where('stripe_id', $data['id'])->first();

        if ($existing) {
            return $existing;
        }

        $items = $data['items']['data'];
        $firstItem = $items[0];
        $isSinglePrice = count($items) === 1;

        $subscription = $workspace->subscriptions()->create([
            'stripe_id' => $data['id'],
            'type' => $data['metadata']['type'] ?? $data['metadata']['name'] ?? 'default',
            'stripe_status' => $data['status'],
            'stripe_price' => $isSinglePrice ? $firstItem['price']['id'] : null,
            'quantity' => $isSinglePrice ? ($firstItem['quantity'] ?? null) : null,
            'trial_ends_at' => isset($data['trial_end']) ? Carbon::createFromTimestamp($data['trial_end']) : null,
            'ends_at' => null,
        ]);

        foreach ($items as $item) {
            $subscription->items()->updateOrCreate([
                'stripe_id' => $item['id'],
            ], [
                'stripe_product' => $item['price']['product'],
                'stripe_price' => $item['price']['id'],
                'quantity' => $item['quantity'] ?? null,
            ]);
        }

        return $subscription;
    }
}
