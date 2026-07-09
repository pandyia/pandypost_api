<?php

namespace App\Services\Billing\Admin;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use App\Exceptions\BillingException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Encapsula as chamadas ao Stripe para o catálogo (Products/Prices) do Admin.
 *
 * Único ponto do sistema que fala com a API do Stripe nesta fase. Quando
 * `services.stripe.enabled` é false (dev/testes), nenhuma chamada externa é
 * feita e ids locais `dev_...` são gerados — espelha o `skip_gateway` do Django.
 *
 * Na fase Cliente/Webhook, quando o Cashier for instalado, o método `client()`
 * pode passar a resolver `Laravel\Cashier\Cashier::stripe()`.
 */
class StripeCatalogService
{
    public function enabled(): bool
    {
        return (bool) config('services.stripe.enabled');
    }

    public function createProduct(string $name, ?string $description = null, array $metadata = []): string
    {
        if (! $this->enabled()) {
            return 'dev_prod_' . Str::lower(Str::random(14));
        }

        return $this->call(fn () => $this->client()->products->create(array_filter([
            'name' => $name,
            'description' => $description,
            'metadata' => $metadata,
        ], fn ($value) => $value !== null))->id);
    }

    public function updateProduct(?string $productId, array $attributes): void
    {
        if ($this->isLocalId($productId)) {
            return;
        }

        $this->call(fn () => $this->client()->products->update($productId, $attributes));
    }

    public function archiveProduct(?string $productId): void
    {
        $this->updateProduct($productId, ['active' => false]);
    }

    public function createPrice(
        string $productId,
        int $amount,
        Currency $currency,
        BillingFrequency $frequency,
        array $metadata = []
    ): string {
        if (! $this->enabled()) {
            return 'dev_price_' . Str::lower(Str::random(14));
        }

        return $this->call(fn () => $this->client()->prices->create([
            'product' => $productId,
            'unit_amount' => $amount,
            'currency' => $currency->value,
            'recurring' => ['interval' => $frequency->stripeInterval()],
            'metadata' => $metadata,
        ])->id);
    }

    public function archivePrice(?string $priceId): void
    {
        if ($this->isLocalId($priceId)) {
            return;
        }

        $this->call(fn () => $this->client()->prices->update($priceId, ['active' => false]));
    }

    /**
     * ids gerados localmente (modo desativado) não existem no Stripe e nunca
     * devem ser enviados à API.
     */
    private function isLocalId(?string $id): bool
    {
        return ! $this->enabled() || $id === null || Str::startsWith($id, 'dev_');
    }

    private function client(): \Stripe\StripeClient
    {
        return new \Stripe\StripeClient((string) config('services.stripe.secret'));
    }

    /**
     * Executa a chamada ao gateway convertendo qualquer falha em BillingException.
     */
    private function call(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            report($e);
            throw BillingException::gatewayError($e->getMessage());
        }
    }
}
