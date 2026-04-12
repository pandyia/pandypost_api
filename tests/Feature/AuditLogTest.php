<?php

use App\Models\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['audit.console' => true]);
});

test('pode listar logs de auditoria no formato flat', function () {
    $user = createUserWithPermissions(['logs.view'], false);
    $workspace = $user->currentAccess->workspace;

    $this->actingAs($user);

    Audit::withoutGlobalScopes()->delete();

    $workspace->update(['name' => 'Novo Nome Workspace']);

    $response = $this->getJson('/api/logs');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'uuid',
                    'action',
                    'entity_type',
                    'entity_uuid',
                    'entity_name',
                    'actor_uuid',
                    'actor_name',
                    'actor_email',
                    'old_values',
                    'new_values',
                    'tags',
                    'created_at',
                ]
            ]
        ]);

    $first = $response->json('data.0');
    expect($first['action'])->toBe('updated');
    expect($first['entity_type'])->toBe('workspace');
    expect($first['actor_name'])->toBe($user->name);
    expect($first['actor_email'])->toBe($user->email);
    expect($first['new_values'])->toHaveKey('name');
    expect($response->json('data'))->toHaveCount(1);
});
