<?php

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Price;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePrice(): Price
{
    $plan = Plan::create([
        'name' => 'Pro',
        'gateway_product_id' => 'dev_prod_1',
    ]);

    return $plan->prices()->create([
        'amount' => 4990,
        'currency' => 'brl',
        'frequency' => 'monthly',
        'trial_period_days' => 0,
        'gateway_price_id' => 'price_test_1',
        'is_active' => true,
    ]);
}

function activeSubscriptionFor(Workspace $workspace, string $stripePrice = 'price_test_1'): void
{
    Subscription::forceCreate([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_client_1',
        'stripe_status' => 'active',
        'stripe_price' => $stripePrice,
        'quantity' => 1,
    ]);
}

test('assinatura atual retorna 404 quando o workspace não tem assinatura', function () {
    $user = createUserWithPermissions(['billing.view']);

    $this->withToken($user->test_token)->getJson('/api/billing/subscription')
        ->assertStatus(404)
        ->assertJsonPath('error', 'no_active_subscription');
});

test('assinatura atual retorna o estado derivado + plano/preço', function () {
    $user = createUserWithPermissions(['billing.view']);
    $price = makePrice();
    activeSubscriptionFor($user->resolveCurrentAccess()->workspace, $price->gateway_price_id);

    $this->withToken($user->test_token)->getJson('/api/billing/subscription')
        ->assertStatus(200)
        ->assertJsonPath('data.billing_status', 'active')
        ->assertJsonPath('data.plan.name', 'Pro')
        ->assertJsonPath('data.price.amount', 4990);
});

test('checkout é bloqueado (409) quando o workspace já tem assinatura', function () {
    $user = createUserWithPermissions(['billing.view', 'billing.manage']);
    $price = makePrice();
    activeSubscriptionFor($user->resolveCurrentAccess()->workspace, $price->gateway_price_id);

    $this->withToken($user->test_token)
        ->postJson('/api/billing/subscription/checkout', ['price' => $price->uuid])
        ->assertStatus(409)
        ->assertJsonPath('error', 'already_subscribed');
});

test('checkout valida o preço', function () {
    $user = createUserWithPermissions(['billing.manage']);

    $this->withToken($user->test_token)
        ->postJson('/api/billing/subscription/checkout', ['price' => 'inexistente'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('price');
});

test('histórico de pagamentos lista apenas os do workspace atual', function () {
    $user = createUserWithPermissions(['billing.view']);
    $workspace = $user->resolveCurrentAccess()->workspace;

    Payment::create([
        'workspace_id' => $workspace->id,
        'status' => 'paid',
        'amount' => 4990,
        'currency' => 'brl',
        'gateway_invoice_id' => 'in_1',
    ]);

    // pagamento de outro workspace não deve aparecer
    $other = Workspace::withoutGlobalScopes()->create(['name' => 'Other', 'is_personal_team' => true]);
    Payment::create([
        'workspace_id' => $other->id,
        'status' => 'paid',
        'amount' => 100,
        'currency' => 'brl',
        'gateway_invoice_id' => 'in_2',
    ]);

    $this->withToken($user->test_token)->getJson('/api/billing/payments')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', 4990);
});

test('acesso ao financeiro do cliente exige permissão billing', function () {
    $user = createUserWithPermissions([]);

    $this->withToken($user->test_token)->getJson('/api/billing/payments')
        ->assertStatus(403);
});
