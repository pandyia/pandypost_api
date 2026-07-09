<?php

namespace App\Services\Billing\Tenant;

use App\Exceptions\BillingException;
use App\Models\Price;
use App\Models\Workspace;

/**
 * Inicia a assinatura via Stripe Checkout (Cashier). O estado real da
 * assinatura é sincronizado depois pelos webhooks (fase 2).
 */
class CheckoutService
{
    /**
     * Cria a Checkout Session (mode=subscription) e retorna a URL hospedada.
     *
     * Regra: uma assinatura ativa por workspace.
     */
    public function start(Workspace $workspace, Price $price): string
    {
        if ($workspace->subscribed('default')) {
            throw BillingException::alreadySubscribed();
        }

        $builder = $workspace->newSubscription('default', $price->gateway_price_id);

        if ($price->trial_period_days > 0) {
            $builder->trialDays($price->trial_period_days);
        }

        $checkout = $builder
            ->withMetadata([
                'workspace_uuid' => $workspace->uuid,
                'price_uuid' => $price->uuid,
            ])
            ->checkout([
                'success_url' => config('services.stripe.checkout_success_url') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('services.stripe.checkout_cancel_url'),
            ]);

        return $checkout->url;
    }
}
