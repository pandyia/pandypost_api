<?php

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| QA — validação dos FormRequests de Role (spec 022, RF-5 / divergência Q2)
|--------------------------------------------------------------------------
|
| A divergência aprovada na Q2 diz que payload malformado em POST /roles e
| PATCH /roles/{uuid} passa a responder 422 (antes: 500 "Undefined array
| key"). Nenhum teste travava esse contrato novo — estes travam.
| O happy path, o 403 e o isolamento entre workspaces seguem cobertos em
| RoleCrudTest (20 casos, intactos).
*/

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

describe('validação do POST /roles (divergência Q2 da spec 022)', function () {

    it('retorna 422 quando o nome não é enviado e não cria nada', function () {
        $user = createUserWithPermissions(['roles.create']);
        $rolesBefore = Role::withoutGlobalScopes()->count();

        $this->withToken($user->test_token)
            ->postJson('/api/roles', ['permissions' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        expect(Role::withoutGlobalScopes()->count())->toBe($rolesBefore);
    });

    it('retorna 422 quando o nome não é uma string', function () {
        $user = createUserWithPermissions(['roles.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/roles', ['name' => ['array', 'invalida']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('retorna 422 quando permissions não é um array', function () {
        $user = createUserWithPermissions(['roles.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/roles', ['name' => 'Redator', 'permissions' => 'posts.view'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);
    });

    it('retorna 422 quando permissions tem item que não é string', function () {
        $user = createUserWithPermissions(['roles.create']);
        $rolesBefore = Role::withoutGlobalScopes()->count();

        $this->withToken($user->test_token)
            ->postJson('/api/roles', ['name' => 'Redator', 'permissions' => [['aninhado']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);

        expect(Role::withoutGlobalScopes()->count())->toBe($rolesBefore);
    });
});

describe('validação do PATCH /roles/{uuid} (divergência Q2 da spec 022)', function () {

    it('retorna 422 quando o nome não é uma string e não altera o perfil', function () {
        $user = createUserWithPermissions(['roles.update']);
        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Perfil Original',
            'workspace_id' => $user->currentAccess->workspace_id,
        ]);

        $this->withToken($user->test_token)
            ->patchJson("/api/roles/{$role->uuid}", ['name' => 12345])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        expect($role->fresh()->name)->toBe('Perfil Original');
    });

    it('retorna 422 quando permissions não é um array', function () {
        $user = createUserWithPermissions(['roles.update']);
        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Perfil Original',
            'workspace_id' => $user->currentAccess->workspace_id,
        ]);

        $this->withToken($user->test_token)
            ->patchJson("/api/roles/{$role->uuid}", ['permissions' => 'posts.view'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);
    });
});
