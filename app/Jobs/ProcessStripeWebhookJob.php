<?php

namespace App\Jobs;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Processa os efeitos custom de um evento de webhook do Stripe (tabela Payment).
 * A sincronização da Subscription em si é feita nativamente pelo Cashier.
 *
 * NOTA: os handlers leem o payload de invoice/checkout do Stripe. Validar contra
 * eventos reais (Stripe CLI / test mode) ao ligar o gateway.
 */
class ProcessStripeWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $eventId = $this->payload['id'] ?? null;

        // Idempotência (camada de cache): processa cada event.id uma única vez.
        if ($eventId && ! Cache::add("stripe:webhook:{$eventId}", true, now()->addDay())) {
            return;
        }

        $type = $this->payload['type'] ?? null;
        $object = $this->payload['data']['object'] ?? [];

        match ($type) {
            'invoice.created' => $this->upsertInvoicePayment($object, PaymentStatus::PENDING),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($object),
            'invoice.payment_failed' => $this->upsertInvoicePayment($object, PaymentStatus::FAILED),
            'invoice.voided' => $this->upsertInvoicePayment($object, PaymentStatus::VOID),
            'checkout.session.expired' => $this->handleCheckoutExpired($object),
            default => null,
        };
    }

    private function handlePaymentSucceeded(array $invoice): void
    {
        $this->upsertInvoicePayment($invoice, PaymentStatus::PAID, [
            'paid_at' => $this->timestamp($invoice['status_transitions']['paid_at'] ?? null) ?? now(),
        ]);
    }

    /**
     * Cria/atualiza (find-or-create por gateway_invoice_id) o Payment a partir
     * de uma invoice do Stripe. Idempotente por natureza.
     */
    private function upsertInvoicePayment(array $invoice, PaymentStatus $status, array $extra = []): void
    {
        $invoiceId = $invoice['id'] ?? null;
        $workspace = $this->resolveWorkspace($invoice);

        if (! $invoiceId || ! $workspace) {
            return; // evento órfão — nada a fazer
        }

        $subscription = $this->resolveSubscription($invoice);

        DB::transaction(function () use ($invoice, $invoiceId, $workspace, $subscription, $status, $extra) {
            Payment::updateOrCreate(
                ['gateway_invoice_id' => $invoiceId],
                array_merge([
                    'workspace_id' => $workspace->id,
                    'subscription_id' => $subscription?->id,
                    'status' => $status,
                    'amount' => (int) ($invoice['amount_paid'] ?? $invoice['amount_due'] ?? 0),
                    'currency' => Currency::tryFrom($invoice['currency'] ?? '')?->value,
                    'gateway_hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
                    'gateway_invoice_pdf' => $invoice['invoice_pdf'] ?? null,
                    'gateway_payment_intent_id' => $invoice['payment_intent'] ?? null,
                    'period_start' => $this->timestamp($invoice['period_start'] ?? null),
                    'period_end' => $this->timestamp($invoice['period_end'] ?? null),
                ], $extra),
            );
        });
    }

    private function handleCheckoutExpired(array $session): void
    {
        $sessionId = $session['id'] ?? null;

        if (! $sessionId) {
            return;
        }

        Payment::where('gateway_checkout_session_id', $sessionId)
            ->update(['status' => PaymentStatus::EXPIRED]);
    }

    private function resolveWorkspace(array $object): ?Workspace
    {
        $customerId = $object['customer'] ?? null;

        return $customerId ? Workspace::where('stripe_id', $customerId)->first() : null;
    }

    private function resolveSubscription(array $invoice): ?Subscription
    {
        $subscriptionId = $invoice['subscription']
            ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);

        return $subscriptionId ? Subscription::where('stripe_id', $subscriptionId)->first() : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_int($value) ? Carbon::createFromTimestamp($value) : null;
    }
}
