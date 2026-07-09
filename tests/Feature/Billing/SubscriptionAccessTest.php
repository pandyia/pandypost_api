<?php

use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Rota temporária protegida pelo middleware de enforcement.
    Route::middleware(['auth:sanctum', 'subscribed'])
        ->get('/api/_test/guarded', fn () => response()->json(['ok' => true]));
});

test('workspace sem assinatura válida recebe 402', function () {
    $user = createUserWithPermissions([]);

    $this->withToken($user->test_token)->getJson('/api/_test/guarded')
        ->assertStatus(402);
});

test('workspace com assinatura ativa passa pelo middleware', function () {
    $user = createUserWithPermissions([]);
    $workspace = $user->resolveCurrentAccess()->workspace;

    Subscription::forceCreate([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_active_1',
        'stripe_status' => 'active',
        'stripe_price' => 'price_1',
        'quantity' => 1,
    ]);

    $this->withToken($user->test_token)->getJson('/api/_test/guarded')
        ->assertStatus(200)
        ->assertJsonPath('ok', true);
});

test('workspace com assinatura em past_due mantém acesso (janela de retentativas)', function () {
    $user = createUserWithPermissions([]);
    $workspace = $user->resolveCurrentAccess()->workspace;

    Subscription::forceCreate([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_pastdue_1',
        'stripe_status' => 'past_due',
        'stripe_price' => 'price_1',
        'quantity' => 1,
    ]);

    $this->withToken($user->test_token)->getJson('/api/_test/guarded')
        ->assertStatus(200);
});
