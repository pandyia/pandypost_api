<?php

use App\Models\Plan;
use App\Models\Price;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePriceIn(string $currency, string $gatewayPriceId): Price
{
    $plan = Plan::create([
        'name' => 'Pro',
        'gateway_product_id' => 'dev_prod_currency',
    ]);

    return $plan->prices()->create([
        'amount' => 4990,
        'currency' => $currency,
        'frequency' => 'monthly',
        'trial_period_days' => 0,
        'gateway_price_id' => $gatewayPriceId,
        'is_active' => true,
    ]);
}

function subscribeWorkspaceTo(Workspace $workspace, string $gatewayPriceId, string $status = 'active'): Subscription
{
    return Subscription::forceCreate([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_currency_' . $gatewayPriceId,
        'stripe_status' => $status,
        'stripe_price' => $gatewayPriceId,
        'quantity' => 1,
    ]);
}

test('workspace sem assinatura usa a moeda default do cashier', function () {
    config(['cashier.currency' => 'brl']);
    $workspace = createUserWithPermissions([])->resolveCurrentAccess()->workspace;

    expect($workspace->preferredCurrency())->toBe('brl');
});

test('workspace herda a moeda do Price da assinatura, nao a do config', function () {
    config(['cashier.currency' => 'brl']);
    $workspace = createUserWithPermissions([])->resolveCurrentAccess()->workspace;
    makePriceIn('eur', 'price_eur_1');
    subscribeWorkspaceTo($workspace, 'price_eur_1');

    expect($workspace->preferredCurrency())->toBe('eur');
});

test('moeda continua resolvendo com o Price arquivado (soft deleted)', function () {
    config(['cashier.currency' => 'brl']);
    $workspace = createUserWithPermissions([])->resolveCurrentAccess()->workspace;
    $price = makePriceIn('gbp', 'price_gbp_1');
    subscribeWorkspaceTo($workspace, 'price_gbp_1');
    $price->delete();

    expect($workspace->fresh()->preferredCurrency())->toBe('gbp');
});

test('assinatura cancelada mantem a moeda travada pelo Stripe', function () {
    config(['cashier.currency' => 'brl']);
    $workspace = createUserWithPermissions([])->resolveCurrentAccess()->workspace;
    makePriceIn('usd', 'price_usd_1');
    subscribeWorkspaceTo($workspace, 'price_usd_1', 'canceled');

    expect($workspace->preferredCurrency())->toBe('usd');
});

test('gateway_price_id orfao cai no default em vez de estourar', function () {
    config(['cashier.currency' => 'brl']);
    $workspace = createUserWithPermissions([])->resolveCurrentAccess()->workspace;
    subscribeWorkspaceTo($workspace, 'price_que_nao_existe');

    expect($workspace->preferredCurrency())->toBe('brl');
});
