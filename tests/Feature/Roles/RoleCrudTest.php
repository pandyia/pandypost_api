<?php

use App\Models\Access;
use App\Models\Permission;
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

// LISTAR (index)
// ---------------------------------------------------------------------------

describe('listar perfis', function () {

    it('retorna a lista paginada de perfis do workspace autenticado', function () {
        $user = createUserWithPermissions(['roles.view']);
        $workspaceId = $user->currentAccess->workspace_id;

        Role::withoutGlobalScopes()->create(['name' => 'Editor', 'workspace_id' => $workspaceId]);
        Role::withoutGlobalScopes()->create(['name' => 'Viewer', 'workspace_id' => $workspaceId]);

        $this->withToken($user->test_token)
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'name', 'permissions', 'user_count', 'users'],
                ],
            ]);
    });

    it('não enxerga perfis de outros workspaces', function () {
        $user = createUserWithPermissions(['roles.view']);

        $otherWorkspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Other Workspace',
            'is_personal_team' => false,
        ]);
        Role::withoutGlobalScopes()->create([
            'name' => 'Perfil Alheio',
            'workspace_id' => $otherWorkspace->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/roles')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        expect($names)->not->toContain('Perfil Alheio');
    });

    it('retorna 401 quando não está autenticado', function () {
        $this->getJson('/api/roles')->assertUnauthorized();
    });

    it('retorna 403 quando não tem a permissão roles.view', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)
            ->getJson('/api/roles')
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// CRIAR (store)
// ---------------------------------------------------------------------------

describe('criar perfil', function () {

    it('cria um perfil com nome e permissões', function () {
        permission('posts.view');
        $user = createUserWithPermissions(['roles.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/roles', [
                'name' => 'Redator',
                'permissions' => ['posts.view'],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Perfil criado com sucesso')
            ->assertJsonPath('data.name', 'Redator')
            ->assertJsonStructure(['data' => ['uuid', 'name', 'permissions', 'user_count', 'users']]);

        $this->assertDatabaseHas('roles', ['name' => 'Redator']);
    });

    it('cria um perfil sem informar permissões', function () {
        $user = createUserWithPermissions(['roles.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/roles', ['name' => 'Leitor'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Leitor');

        $this->assertDatabaseHas('roles', ['name' => 'Leitor']);
    });

    it('retorna 422 quando o nome já existe no mesmo workspace', function () {
        $user = createUserWithPermissions(['roles.create']);
        $workspaceId = $user->currentAccess->workspace_id;

        Role::withoutGlobalScopes()->create(['name' => 'Admin', 'workspace_id' => $workspaceId]);

        $this->withToken($user->test_token)
            ->postJson('/api/roles', ['name' => 'Admin'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'role_name_already_exists');
    });

    it('cria perfil com nome igual ao de outro workspace sem conflito', function () {
        $user = createUserWithPermissions(['roles.create']);

        $otherWorkspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Other Workspace',
            'is_personal_team' => false,
        ]);
        Role::withoutGlobalScopes()->create(['name' => 'Admin', 'workspace_id' => $otherWorkspace->id]);

        $this->withToken($user->test_token)
            ->postJson('/api/roles', ['name' => 'Admin'])
            ->assertCreated();
    });

    it('retorna 401 quando não está autenticado', function () {
        $this->postJson('/api/roles', ['name' => 'Qualquer'])->assertUnauthorized();
    });

    it('retorna 403 quando não tem a permissão roles.create', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)
            ->postJson('/api/roles', ['name' => 'Qualquer'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// EXIBIR (show)
// ---------------------------------------------------------------------------

describe('exibir perfil', function () {

    it('exibe os dados de um perfil pelo uuid', function () {
        $user = createUserWithPermissions(['roles.view']);
        $workspaceId = $user->currentAccess->workspace_id;

        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Gerente',
            'workspace_id' => $workspaceId,
        ]);

        $this->withToken($user->test_token)
            ->getJson("/api/roles/{$role->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $role->uuid)
            ->assertJsonPath('data.name', 'Gerente')
            ->assertJsonStructure(['data' => ['uuid', 'name', 'permissions', 'user_count', 'users']]);
    });

    it('retorna 404 para uuid inexistente', function () {
        $user = createUserWithPermissions(['roles.view']);
        $uuid = Str::uuid();

        $this->withToken($user->test_token)
            ->getJson("/api/roles/{$uuid}")
            ->assertNotFound();
    });

    it('retorna 404 ao tentar exibir perfil de outro workspace', function () {
        $user = createUserWithPermissions(['roles.view']);

        $otherWorkspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Other Workspace',
            'is_personal_team' => false,
        ]);
        $foreignRole = Role::withoutGlobalScopes()->create([
            'name' => 'Perfil Alheio',
            'workspace_id' => $otherWorkspace->id,
        ]);

        // BelongsToWorkspace global scope filters the record → 404
        $this->withToken($user->test_token)
            ->getJson("/api/roles/{$foreignRole->uuid}")
            ->assertNotFound();
    });
});

// ---------------------------------------------------------------------------
// EDITAR (update)
// ---------------------------------------------------------------------------

describe('editar perfil', function () {

    it('atualiza o nome e as permissões de um perfil', function () {
        permission('posts.create');
        $user = createUserWithPermissions(['roles.update']);
        $workspaceId = $user->currentAccess->workspace_id;

        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Editor Antigo',
            'workspace_id' => $workspaceId,
        ]);

        $this->withToken($user->test_token)
            ->putJson("/api/roles/{$role->uuid}", [
                'name' => 'Editor Novo',
                'permissions' => ['posts.create'],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Perfil editado com sucesso')
            ->assertJsonPath('data.name', 'Editor Novo');

        $this->assertDatabaseHas('roles', ['uuid' => $role->uuid, 'name' => 'Editor Novo']);
    });

    it('atualizar mantendo o mesmo nome não gera conflito', function () {
        $user = createUserWithPermissions(['roles.update']);
        $workspaceId = $user->currentAccess->workspace_id;

        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Mesmo Nome',
            'workspace_id' => $workspaceId,
        ]);

        $this->withToken($user->test_token)
            ->putJson("/api/roles/{$role->uuid}", ['name' => 'Mesmo Nome'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mesmo Nome');
    });

    it('retorna 422 ao atualizar para nome já existente no workspace', function () {
        $user = createUserWithPermissions(['roles.update']);
        $workspaceId = $user->currentAccess->workspace_id;

        Role::withoutGlobalScopes()->create(['name' => 'Ocupado', 'workspace_id' => $workspaceId]);
        $role = Role::withoutGlobalScopes()->create(['name' => 'Original', 'workspace_id' => $workspaceId]);

        $this->withToken($user->test_token)
            ->putJson("/api/roles/{$role->uuid}", ['name' => 'Ocupado'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'role_name_already_exists');
    });

    it('retorna 403 quando não tem a permissão roles.update', function () {
        $user = createUserWithoutPermissions();
        $uuid = Str::uuid();

        $this->withToken($user->test_token)
            ->putJson("/api/roles/{$uuid}", ['name' => 'Qualquer'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// DELETAR (destroy)
// ---------------------------------------------------------------------------

describe('deletar perfil', function () {

    it('deleta um perfil sem usuários vinculados', function () {
        $user = createUserWithPermissions(['roles.delete']);
        $workspaceId = $user->currentAccess->workspace_id;

        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Para Deletar',
            'workspace_id' => $workspaceId,
        ]);

        $this->withToken($user->test_token)
            ->deleteJson("/api/roles/{$role->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Registro deletado com sucesso.');

        $this->assertDatabaseMissing('roles', ['uuid' => $role->uuid]);
    });

    it('retorna 409 ao tentar deletar perfil com usuários vinculados', function () {
        $user = createUserWithPermissions(['roles.delete']);
        $workspaceId = $user->currentAccess->workspace_id;

        $targetRole = Role::withoutGlobalScopes()->create([
            'name' => 'Com Usuários',
            'workspace_id' => $workspaceId,
        ]);

        $otherUser = User::factory()->create();
        Access::create([
            'user_id' => $otherUser->id,
            'role_id' => $targetRole->id,
            'workspace_id' => $workspaceId,
        ]);

        $this->withToken($user->test_token)
            ->deleteJson("/api/roles/{$targetRole->uuid}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'profile_has_linked_users');
    });

    it('retorna 403 quando não tem a permissão roles.delete', function () {
        $user = createUserWithoutPermissions();
        $uuid = Str::uuid();

        $this->withToken($user->test_token)
            ->deleteJson("/api/roles/{$uuid}")
            ->assertForbidden();
    });

    it('retorna 404 ao tentar deletar perfil de outro workspace', function () {
        $user = createUserWithPermissions(['roles.delete']);

        $otherWorkspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'External Workspace',
            'is_personal_team' => false,
        ]);
        $foreignRole = Role::withoutGlobalScopes()->create([
            'name' => 'Externo',
            'workspace_id' => $otherWorkspace->id,
        ]);

        $this->withToken($user->test_token)
            ->deleteJson("/api/roles/{$foreignRole->uuid}")
            ->assertNotFound();
    });
});
