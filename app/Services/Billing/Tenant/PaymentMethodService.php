<?php

namespace App\Services\Billing\Tenant;

use App\Exceptions\BillingException;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Métodos de pagamento do cliente, ao vivo via Cashier (sem tabela Card local).
 * Dados sensíveis nunca trafegam pelo backend — só o payment_method_id (pm_...).
 */
class PaymentMethodService
{
    /**
     * SetupIntent para o Stripe.js coletar o cartão no front.
     */
    public function setupIntentSecret(Workspace $workspace): string
    {
        $workspace->createOrGetStripeCustomer();

        return $workspace->createSetupIntent()->client_secret;
    }

    public function list(Workspace $workspace): Collection
    {
        if (! $workspace->hasStripeId()) {
            return collect();
        }

        $defaultId = $workspace->defaultPaymentMethod()?->id;

        return collect($workspace->paymentMethods())->map(fn ($method) => [
            'method' => $method,
            'is_default' => $method->id === $defaultId,
        ]);
    }

    public function add(Workspace $workspace, string $paymentMethodId, bool $makeDefault = false): void
    {
        $workspace->createOrGetStripeCustomer();
        $workspace->addPaymentMethod($paymentMethodId);

        if ($makeDefault || ! $workspace->defaultPaymentMethod()) {
            $workspace->updateDefaultPaymentMethod($paymentMethodId);
        }
    }

    public function setDefault(Workspace $workspace, string $paymentMethodId): void
    {
        $workspace->updateDefaultPaymentMethod($paymentMethodId);
    }

    public function remove(Workspace $workspace, string $paymentMethodId): void
    {
        if ($workspace->defaultPaymentMethod()?->id === $paymentMethodId) {
            throw BillingException::defaultCardRequired();
        }

        $workspace->deletePaymentMethod($paymentMethodId);
    }
}
