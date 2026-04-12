<?php

use App\Models\Access;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Setup global
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

// ---------------------------------------------------------------------------
// LISTAR USUÁRIOS (index)
// ---------------------------------------------------------------------------

describe('listar usuários', function () {

    it('retorna a lista paginada de usuários do workspace atual', function () {
        $user = createUserWithPermissions(['users.view']);
        $workspaceId = $user->currentAccess->workspace_id;
        $workspace = Workspace::withoutGlobalScopes()->find($workspaceId);

        $member = addUserToWorkspace($workspace);

        $response = $this->withToken($user->test_token)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'name', 'email'],
                ],
            ]);

        $uuids = collect($response->json('data'))->pluck('uuid');
        expect($uuids)->toContain($user->uuid);
        expect($uuids)->toContain($member->uuid);
    });

    it('não enxerga usuários de outros workspaces', function () {
        $user = createUserWithPermissions(['users.view']);

        // Criar outro workspace com outro usuário
        $otherWorkspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Other Workspace',
            'is_personal_team' => false,
        ]);
        $outsider = addUserToWorkspace($otherWorkspace);

        $response = $this->withToken($user->test_token)
            ->getJson('/api/users')
            ->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid');
        expect($uuids)->not->toContain($outsider->uuid);
    });

    it('retorna 401 quando não está autenticado', function () {
        $this->getJson('/api/users')->assertUnauthorized();
    });

    it('retorna 403 quando não tem a permissão users.view', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)
            ->getJson('/api/users')
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// REMOVER USUÁRIO DO WORKSPACE (destroy)
// ---------------------------------------------------------------------------

describe('remover usuário do workspace', function () {

    it('remove um usuário do workspace com sucesso', function () {
        $user = createUserWithPermissions(['users.delete', 'users.view']);
        $workspaceId = $user->currentAccess->workspace_id;
        $workspace = Workspace::withoutGlobalScopes()->find($workspaceId);

        $targetUser = addUserToWorkspace($workspace);

        $this->withToken($user->test_token)
            ->deleteJson("/api/users/{$targetUser->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Usuário removido com sucesso');

        $this->assertDatabaseMissing('accesses', [
            'user_id' => $targetUser->id,
            'workspace_id' => $workspaceId,
        ]);
    });

    it('desloga o usuário removido e redireciona para workspace pessoal', function () {
        $user = createUserWithPermissions(['users.delete', 'users.view']);
        $workspaceId = $user->currentAccess->workspace_id;
        $workspace = Workspace::withoutGlobalScopes()->find($workspaceId);

        // Criar workspace pessoal do targetUser
        $personalWorkspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Personal do Target',
            'is_personal_team' => true,
        ]);

        $targetUser = User::factory()->create();

        // Vincula ao workspace pessoal
        $personalRole = Role::withoutGlobalScopes()->create([
            'name' => 'Personal Role ' . uniqid(),
            'workspace_id' => $personalWorkspace->id,
        ]);
        $personalAccess = Access::create([
            'user_id' => $targetUser->id,
            'role_id' => $personalRole->id,
            'workspace_id' => $personalWorkspace->id,
        ]);

        // Vincula ao workspace do auth user (esse será removido)
        addUserToWorkspace($workspace, $targetUser);

        // Cria token para verificar que será revogado
        $targetUser->createToken('session')->plainTextToken;

        $this->withToken($user->test_token)
            ->deleteJson("/api/users/{$targetUser->uuid}")
            ->assertOk();

        $targetUser->refresh();

        // access_id deve apontar para o workspace pessoal
        expect($targetUser->access_id)->toBe($personalAccess->id);

        // Tokens devem ter sido revogados
        expect($targetUser->tokens()->count())->toBe(0);
    });

    it('retorna 400 ao tentar remover a si mesmo', function () {
        $user = createUserWithPermissions(['users.delete', 'users.view']);

        $this->withToken($user->test_token)
            ->deleteJson("/api/users/{$user->uuid}")
            ->assertStatus(400)
            ->assertJsonPath('error', 'cannot_remove_yourself');
    });

    it('retorna 401 quando não está autenticado', function () {
        $uuid = \Illuminate\Support\Str::uuid();

        $this->deleteJson("/api/users/{$uuid}")
            ->assertUnauthorized();
    });

    it('retorna 403 quando não tem a permissão users.delete', function () {
        $user = createUserWithPermissions(['users.view']);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $targetUser = addUserToWorkspace($workspace);

        $this->withToken($user->test_token)
            ->deleteJson("/api/users/{$targetUser->uuid}")
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// TROCAR PERFIL DO USUÁRIO (changeRole)
// ---------------------------------------------------------------------------

describe('trocar perfil do usuário', function () {

    it('altera o perfil de um usuário no workspace com sucesso', function () {
        $user = createUserWithPermissions(['users.change_role']);
        $workspaceId = $user->currentAccess->workspace_id;
        $workspace = Workspace::withoutGlobalScopes()->find($workspaceId);

        $targetUser = addUserToWorkspace($workspace);

        $newRole = Role::withoutGlobalScopes()->create([
            'name' => 'Novo Perfil ' . uniqid(),
            'workspace_id' => $workspaceId,
        ]);

        $this->withToken($user->test_token)
            ->patchJson("/api/users/{$targetUser->uuid}/role", [
                'role_uuid' => $newRole->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Perfil do usuário atualizado com sucesso');

        $targetAccess = Access::where('user_id', $targetUser->id)
            ->where('workspace_id', $workspaceId)
            ->first();

        expect($targetAccess->role_id)->toBe($newRole->id);
    });

    it('retorna 404 quando o perfil não pertence ao workspace', function () {
        $user = createUserWithPermissions(['users.change_role']);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $targetUser = addUserToWorkspace($workspace);

        // Criar role em outro workspace
        $otherWorkspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Other WS',
            'is_personal_team' => false,
        ]);
        $foreignRole = Role::withoutGlobalScopes()->create([
            'name' => 'Foreign Role',
            'workspace_id' => $otherWorkspace->id,
        ]);

        $this->withToken($user->test_token)
            ->patchJson("/api/users/{$targetUser->uuid}/role", [
                'role_uuid' => $foreignRole->uuid,
            ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'profile_not_found');
    });

    it('retorna 404 quando o uuid do perfil não existe', function () {
        $user = createUserWithPermissions(['users.change_role']);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $targetUser = addUserToWorkspace($workspace);

        $this->withToken($user->test_token)
            ->patchJson("/api/users/{$targetUser->uuid}/role", [
                'role_uuid' => \Illuminate\Support\Str::uuid(),
            ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'profile_not_found');
    });

    it('retorna 401 quando não está autenticado', function () {
        $uuid = \Illuminate\Support\Str::uuid();

        $this->patchJson("/api/users/{$uuid}/role", [
            'role_uuid' => \Illuminate\Support\Str::uuid(),
        ])->assertUnauthorized();
    });

    it('retorna 403 quando não tem a permissão users.change_role', function () {
        $user = createUserWithoutPermissions();
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $targetUser = addUserToWorkspace($workspace);

        $this->withToken($user->test_token)
            ->patchJson("/api/users/{$targetUser->uuid}/role", [
                'role_uuid' => \Illuminate\Support\Str::uuid(),
            ])
            ->assertForbidden();
    });
});
