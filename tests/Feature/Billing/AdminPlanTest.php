<?php

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Gateway desativado nos testes: ids locais dev_..., sem chamadas ao Stripe.
    config(['services.stripe.enabled' => false]);
});

test('admin pode criar um plano e recebe um gateway_product_id', function () {
    $user = createUserWithPermissions(['billing.manage']);

    $response = $this->withToken($user->test_token)->postJson('/api/admin/plans', [
        'name' => 'Pro',
        'description' => 'Plano profissional',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Pro');

    $plan = Plan::first();
    expect($plan)->not->toBeNull()
        ->and($plan->gateway_product_id)->toStartWith('dev_prod_')
        ->and($plan->is_active)->toBeTrue();
});

test('admin pode listar planos', function () {
    $user = createUserWithPermissions(['billing.manage', 'billing.view']);

    $this->withToken($user->test_token)->postJson('/api/admin/plans', ['name' => 'Starter']);
    $this->withToken($user->test_token)->postJson('/api/admin/plans', ['name' => 'Agency']);

    $this->withToken($user->test_token)->getJson('/api/admin/plans')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('admin pode detalhar um plano pelo uuid', function () {
    $user = createUserWithPermissions(['billing.manage', 'billing.view']);

    $create = $this->withToken($user->test_token)->postJson('/api/admin/plans', ['name' => 'Pro']);
    $uuid = $create->json('data.id');

    $this->withToken($user->test_token)->getJson("/api/admin/plans/{$uuid}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $uuid)
        ->assertJsonStructure(['data' => ['id', 'name', 'description', 'is_visible', 'is_active', 'prices']]);
});

test('admin pode atualizar o nome de um plano', function () {
    $user = createUserWithPermissions(['billing.manage']);

    $uuid = $this->withToken($user->test_token)
        ->postJson('/api/admin/plans', ['name' => 'Pro'])
        ->json('data.id');

    $this->withToken($user->test_token)->patchJson("/api/admin/plans/{$uuid}", [
        'name' => 'Pro Plus',
    ])->assertStatus(200)->assertJsonPath('data.name', 'Pro Plus');

    expect(Plan::where('uuid', $uuid)->value('name'))->toBe('Pro Plus');
});

test('admin pode desativar um plano pelo update', function () {
    $user = createUserWithPermissions(['billing.manage']);

    $uuid = $this->withToken($user->test_token)
        ->postJson('/api/admin/plans', ['name' => 'Pro'])
        ->json('data.id');

    $this->withToken($user->test_token)->patchJson("/api/admin/plans/{$uuid}", ['is_active' => false])
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    expect(Plan::where('uuid', $uuid)->value('is_active'))->toBeFalse();
});

test('admin pode reativar um plano pelo update', function () {
    $user = createUserWithPermissions(['billing.manage']);

    $uuid = $this->withToken($user->test_token)
        ->postJson('/api/admin/plans', ['name' => 'Pro'])
        ->json('data.id');

    $this->withToken($user->test_token)->patchJson("/api/admin/plans/{$uuid}", ['is_active' => false])->assertStatus(200);
    $this->withToken($user->test_token)->patchJson("/api/admin/plans/{$uuid}", ['is_active' => true])
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', true);

    expect(Plan::where('uuid', $uuid)->value('is_active'))->toBeTrue();
});

test('plano nunca usado é removido definitivamente (hard delete)', function () {
    $user = createUserWithPermissions(['billing.manage']);

    $uuid = $this->withToken($user->test_token)
        ->postJson('/api/admin/plans', ['name' => 'Pro'])
        ->json('data.id');

    $this->withToken($user->test_token)->deleteJson("/api/admin/plans/{$uuid}")
        ->assertStatus(200);

    expect(Plan::withTrashed()->where('uuid', $uuid)->exists())->toBeFalse();
});

test('usuário sem permissão billing.manage não pode criar plano', function () {
    $user = createUserWithPermissions(['billing.view']);

    $this->withToken($user->test_token)->postJson('/api/admin/plans', ['name' => 'Pro'])
        ->assertStatus(403);
});

test('nome do plano é obrigatório', function () {
    $user = createUserWithPermissions(['billing.manage']);

    $this->withToken($user->test_token)->postJson('/api/admin/plans', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});
