<?php

namespace App\Services\Billing\Admin;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use App\Exceptions\BillingException;
use App\Models\Plan;
use App\Models\Price;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PriceService extends BaseService
{
    public function __construct(
        Price $price,
        private readonly StripeCatalogService $gateway,
    ) {
        parent::__construct($price);
    }

    public function forPlan(Plan $plan): Collection
    {
        return $plan->prices()->orderByDesc('created_at')->get();
    }

    public function versions(Price $price): Collection
    {
        return $price->histories()->orderByDesc('created_at')->get();
    }

    /**
     * Cria o Price no Stripe e persiste o preço local.
     */
    public function createPrice(Plan $plan, array $data): Price
    {
        return DB::transaction(function () use ($plan, $data) {
            $currency = Currency::from($data['currency']);
            $frequency = BillingFrequency::from($data['frequency']);

            $priceId = $this->gateway->createPrice(
                $plan->gateway_product_id,
                (int) $data['amount'],
                $currency,
                $frequency,
                ['plan_uuid' => $plan->uuid],
            );

            return $plan->prices()->create([
                'amount' => (int) $data['amount'],
                'currency' => $currency->value,
                'frequency' => $frequency->value,
                'trial_period_days' => (int) ($data['trial_period_days'] ?? 0),
                'gateway_price_id' => $priceId,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Preços no Stripe são imutáveis. "Editar" = snapshot do antigo no histórico,
     * arquivar o antigo no Stripe, criar um novo e apontar o registro local para ele.
     */
    public function updatePrice(Plan $plan, Price $price, array $data): Price
    {
        $this->assertBelongsToPlan($plan, $price);

        return DB::transaction(function () use ($plan, $price, $data) {
            $amount = (int) ($data['amount'] ?? $price->amount);
            $currency = Currency::from($data['currency'] ?? $price->currency->value);
            $frequency = BillingFrequency::from($data['frequency'] ?? $price->frequency->value);
            $trialDays = (int) ($data['trial_period_days'] ?? $price->trial_period_days);

            // 1. snapshot do estado atual
            $price->histories()->create([
                'gateway_price_id' => $price->gateway_price_id,
                'amount' => $price->amount,
                'currency' => $price->currency->value,
                'frequency' => $price->frequency->value,
                'trial_period_days' => $price->trial_period_days,
                'archived_at' => now(),
                'reason' => $data['reason'] ?? null,
            ]);

            // 2. arquiva o antigo no Stripe
            $this->gateway->archivePrice($price->gateway_price_id);

            // 3. cria o novo no Stripe
            $newPriceId = $this->gateway->createPrice(
                $plan->gateway_product_id,
                $amount,
                $currency,
                $frequency,
                ['plan_uuid' => $plan->uuid],
            );

            // 4. aponta o registro local para o novo preço
            $price->update([
                'amount' => $amount,
                'currency' => $currency->value,
                'frequency' => $frequency->value,
                'trial_period_days' => $trialDays,
                'gateway_price_id' => $newPriceId,
                'is_active' => true,
            ]);

            return $price->refresh();
        });
    }

    /**
     * Deleção de preço:
     * - já utilizado em assinatura/pagamento → soft delete (arquiva no Stripe);
     * - único ativo do plano para a combinação (moeda, frequência) → 409;
     * - nunca utilizado → hard delete (remove histórico e registro local).
     */
    public function deletePrice(Plan $plan, Price $price): void
    {
        $this->assertBelongsToPlan($plan, $price);

        if ($this->isOnlyActiveForCombo($price)) {
            throw BillingException::priceLastActiveInUse();
        }

        DB::transaction(function () use ($price) {
            $this->gateway->archivePrice($price->gateway_price_id);

            if ($this->isInUse($price)) {
                $price->update(['is_active' => false]);
                $price->delete(); // soft delete
                return;
            }

            $price->histories()->delete();
            $price->forceDelete();
        });
    }

    private function assertBelongsToPlan(Plan $plan, Price $price): void
    {
        if ($price->plan_id !== $plan->id) {
            throw BillingException::pricePlanMismatch();
        }
    }

    /**
     * É o único preço ATIVO do plano para a combinação (moeda, frequência)?
     */
    private function isOnlyActiveForCombo(Price $price): bool
    {
        if (! $price->is_active) {
            return false;
        }

        return ! Price::where('plan_id', $price->plan_id)
            ->where('currency', $price->currency->value)
            ->where('frequency', $price->frequency->value)
            ->where('is_active', true)
            ->where('id', '!=', $price->id)
            ->exists();
    }

    /**
     * Indica se o preço já foi usado em assinatura/pagamento.
     *
     * DEFERIDO: o vínculo preço↔assinatura/pagamento entra na fase Cliente/Webhook
     * (tabela Payment / Subscription do Cashier referenciando o preço). Enquanto
     * isso, nenhum preço é considerado "em uso" — a estrutura já fica pronta.
     */
    private function isInUse(Price $price): bool
    {
        return false;
    }
}
