<?php

namespace App\Listeners;

use App\Jobs\ProcessStripeWebhookJob;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Ponto de extensão dos webhooks: o Cashier já sincroniza nativamente a
 * Subscription (status/períodos/trial/cancelamento). Aqui apenas despachamos
 * um Job para os efeitos CUSTOM (tabela Payment) nos eventos de invoice/checkout.
 */
class HandleStripeWebhook
{
    /**
     * Eventos que geram efeitos custom nossos.
     */
    private const HANDLED = [
        'invoice.created',
        'invoice.payment_succeeded',
        'invoice.payment_failed',
        'invoice.voided',
        'checkout.session.expired',
    ];

    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (in_array($type, self::HANDLED, true)) {
            ProcessStripeWebhookJob::dispatch($event->payload);
        }
    }
}
