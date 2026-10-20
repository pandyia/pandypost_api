<?php

use App\Models\Plan;
use App\Models\Price;
use App\Models\PriceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function gatewayTestPrice(Plan $plan, ?string $gatewayPriceId): Price
{
    return Price::create([
        'plan_id' => $plan->id,
        'amount' => 1000,
        'currency' => 'brl',
        'frequency' => 'monthly',
        'gateway_price_id' => $gatewayPriceId,
    ]);
}

function gatewayTestHistory(Price $price, ?string $gatewayPriceId): PriceHistory
{
    return PriceHistory::create([
        'price_id' => $price->id,
        'gateway_price_id' => $gatewayPriceId,
        'amount' => 900,
        'currency' => 'brl',
        'frequency' => 'monthly',
        'archived_at' => now(),
    ]);
}

describe('Plan::gatewayPriceIds', function () {

    it('retorna vazio quando o plano não tem preços', function () {
        $plan = Plan::create(['name' => 'Vazio']);

        expect($plan->gatewayPriceIds())->toBe([]);
    });

    it('reúne preço atual, preço arquivado e histórico, sem duplicar nem trazer nulos', function () {
        $plan = Plan::create(['name' => 'Pro']);

        $current = gatewayTestPrice($plan, 'price_atual');
        $archived = gatewayTestPrice($plan, 'price_arquivado');
        $archived->delete();
        gatewayTestPrice($plan, null);

        gatewayTestHistory($current, 'price_antigo');
        gatewayTestHistory($current, 'price_atual'); // repetido do preço atual
        gatewayTestHistory($current, null);

        $ids = $plan->gatewayPriceIds();

        expect($ids)->toBe(['price_atual', 'price_arquivado', 'price_antigo']);
    });

    it('não mistura preços de outro plano', function () {
        $plan = Plan::create(['name' => 'Pro']);
        $other = Plan::create(['name' => 'Outro']);

        gatewayTestPrice($plan, 'price_meu');
        gatewayTestHistory(gatewayTestPrice($other, 'price_alheio'), 'price_alheio_antigo');

        expect($plan->gatewayPriceIds())->toBe(['price_meu']);
    });
});
