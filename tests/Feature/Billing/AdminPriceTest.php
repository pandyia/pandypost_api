<?php

use App\Models\Plan;
use App\Models\Price;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.enabled' => false]);
});

function createPlanViaApi(string $token): string
{
    return test()->withToken($token)
        ->postJson('/api/admin/plans', ['name' => 'Pro'])
        ->json('data.id');
}

test('admin pode adicionar um preço ao plano', function () {
    $user = createUserWithPermissions(['billing.manage']);
    $planUuid = createPlanViaApi($user->test_token);

    $response = $this->withToken($user->test_token)->postJson("/api/admin/plans/{$planUuid}/prices", [
        'amount' => 4990,
        'currency' => 'brl',
        'frequency' => 'monthly',
        'trial_period_days' => 14,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.amount', 4990)
        ->assertJsonPath('data.currency', 'brl')
        ->assertJsonPath('data.frequency', 'monthly');

    $price = Price::first();
    expect($price->gateway_price_id)->toStartWith('dev_price_')
        ->and($price->trial_period_days)->toBe(14);
});

test('editar preço arquiva o antigo no histórico e cria um novo gateway_price_id', function () {
    $user = createUserWithPermissions(['billing.manage']);
    $planUuid = createPlanViaApi($user->test_token);

    $priceUuid = $this->withToken($user->test_token)
        ->postJson("/api/admin/plans/{$planUuid}/prices", [
            'amount' => 4990, 'currency' => 'brl', 'frequency' => 'monthly',
        ])->json('data.id');

    $originalGatewayId = Price::where('uuid', $priceUuid)->value('gateway_price_id');

    $this->withToken($user->test_token)->patchJson("/api/admin/plans/{$planUuid}/prices/{$priceUuid}", [
        'amount' => 5990,
        'reason' => 'Reajuste anual',
    ])->assertStatus(200)->assertJsonPath('data.amount', 5990);

    $price = Price::where('uuid', $priceUuid)->first();

    expect($price->amount)->toBe(5990)
        ->and($price->gateway_price_id)->not->toBe($originalGatewayId)
        ->and($price->histories()->count())->toBe(1)
        ->and($price->histories()->first()->amount)->toBe(4990)
        ->and($price->histories()->first()->reason)->toBe('Reajuste anual');
});

test('não é possível remover o único preço ativo da combinação moeda/frequência', function () {
    $user = createUserWithPermissions(['billing.manage']);
    $planUuid = createPlanViaApi($user->test_token);

    $priceUuid = $this->withToken($user->test_token)
        ->postJson("/api/admin/plans/{$planUuid}/prices", [
            'amount' => 4990, 'currency' => 'brl', 'frequency' => 'monthly',
        ])->json('data.id');

    $this->withToken($user->test_token)
        ->deleteJson("/api/admin/plans/{$planUuid}/prices/{$priceUuid}")
        ->assertStatus(409)
        ->assertJsonPath('error', 'price_last_active_in_use');
});

test('preço nunca usado com substituto ativo é removido definitivamente', function () {
    $user = createUserWithPermissions(['billing.manage']);
    $planUuid = createPlanViaApi($user->test_token);

    // dois preços na mesma combinação (brl/monthly)
    $first = $this->withToken($user->test_token)
        ->postJson("/api/admin/plans/{$planUuid}/prices", [
            'amount' => 4990, 'currency' => 'brl', 'frequency' => 'monthly',
        ])->json('data.id');

    $this->withToken($user->test_token)
        ->postJson("/api/admin/plans/{$planUuid}/prices", [
            'amount' => 3990, 'currency' => 'brl', 'frequency' => 'monthly',
        ]);

    $this->withToken($user->test_token)
        ->deleteJson("/api/admin/plans/{$planUuid}/prices/{$first}")
        ->assertStatus(200);

    expect(Price::withTrashed()->where('uuid', $first)->exists())->toBeFalse();
});

test('admin lista o histórico de versões de um preço', function () {
    $user = createUserWithPermissions(['billing.manage', 'billing.view']);
    $planUuid = createPlanViaApi($user->test_token);

    $priceUuid = $this->withToken($user->test_token)
        ->postJson("/api/admin/plans/{$planUuid}/prices", [
            'amount' => 4990, 'currency' => 'brl', 'frequency' => 'monthly',
        ])->json('data.id');

    $this->withToken($user->test_token)->patchJson("/api/admin/plans/{$planUuid}/prices/{$priceUuid}", [
        'amount' => 5990,
    ]);

    $this->withToken($user->test_token)
        ->getJson("/api/admin/plans/{$planUuid}/prices/{$priceUuid}/versions")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', 4990);
});
