<?php

namespace Database\Seeders;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use App\Models\Plan;
use App\Models\Price;
use App\Services\Billing\Admin\StripeCatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Cria os planos e seus preços no formato atual do billing.
     *
     * - Plano: metadados + visibilidade (uuid gerado no boot do model).
     * - Price: valor em CENTAVOS, moeda (App\Enums\Currency) e frequência
     *   (App\Enums\BillingFrequency). gateway_price_id fica nulo — é preenchido
     *   ao sincronizar com o Stripe, não no seed.
     *
     * Produtos e preços sem ID do gateway são criados no Stripe (ou recebem ids
     * `dev_...` quando STRIPE_ENABLED=false). Re-seeds não duplicam: só cria o
     * que ainda não tem ID.
     */
    public function run(): void
    {
        $gateway = app(StripeCatalogService::class);

        $plans = [
            [
                'name' => 'Free',
                'description' => 'Plano básico para começar.',
                'is_visible' => true,
                'is_active' => true,
                'prices' => [
                    ['amount' => 0, 'currency' => Currency::BRL, 'frequency' => BillingFrequency::MONTHLY, 'trial_period_days' => 0],
                ],
            ],
            [
                'name' => 'Pro',
                'description' => 'Ideal para criadores de conteúdo.',
                'is_visible' => true,
                'is_active' => true,
                'prices' => [
                    ['amount' => 2990, 'currency' => Currency::BRL, 'frequency' => BillingFrequency::MONTHLY, 'trial_period_days' => 7],
                    ['amount' => 29900, 'currency' => Currency::BRL, 'frequency' => BillingFrequency::YEARLY, 'trial_period_days' => 7],
                ],
            ],
            [
                'name' => 'Agency',
                'description' => 'Para agências e times gerenciando várias contas.',
                'is_visible' => true,
                'is_active' => true,
                'prices' => [
                    ['amount' => 9990, 'currency' => Currency::BRL, 'frequency' => BillingFrequency::MONTHLY, 'trial_period_days' => 0],
                    ['amount' => 99900, 'currency' => Currency::BRL, 'frequency' => BillingFrequency::YEARLY, 'trial_period_days' => 0],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $prices = $planData['prices'];
            unset($planData['prices']);

            $plan = Plan::updateOrCreate(
                ['name' => $planData['name']],
                $planData,
            );

            // Backfill de uuid para planos pré-existentes (seed legado): o uuid é
            // a route key da API, então não pode ficar nulo. O boot hook só gera
            // uuid na criação, então garantimos aqui no caminho de update.
            if (! $plan->uuid) {
                $plan->uuid = (string) Str::uuid();
                $plan->save();
            }

            if (! $plan->gateway_product_id) {
                $plan->gateway_product_id = $gateway->createProduct(
                    $plan->name,
                    $plan->description,
                    ['plan_uuid' => $plan->uuid],
                );
                $plan->save();
            }

            foreach ($prices as $priceData) {
                // O DatabaseSeeder usa WithoutModelEvents, então o hook `creating`
                // que gera o uuid não dispara aqui. Como prices.uuid é NOT NULL,
                // geramos o uuid manualmente — apenas em registros novos, para não
                // alterar a route key em re-seeds.
                $price = Price::firstOrNew([
                    'plan_id' => $plan->id,
                    'currency' => $priceData['currency'],
                    'frequency' => $priceData['frequency'],
                ]);

                $price->fill($priceData);

                if (! $price->uuid) {
                    $price->uuid = (string) Str::uuid();
                }

                $price->save();

                if (! $price->gateway_price_id) {
                    $price->gateway_price_id = $gateway->createPrice(
                        $plan->gateway_product_id,
                        $price->amount,
                        $price->currency instanceof Currency ? $price->currency : Currency::from($price->currency),
                        $price->frequency instanceof BillingFrequency ? $price->frequency : BillingFrequency::from($price->frequency),
                        ['plan_uuid' => $plan->uuid],
                    );
                    $price->save();
                }
            }
        }
    }
}
