<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Models\Role;
use App\Models\Access;

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

describe('listar workspaces', function () {

    it('lista os workspaces do usuário com sucesso', function () {
        $user = createUserWithPermissions(['workspaces.view']);

        $this->withToken($user->test_token)
            ->getJson('/api/workspaces')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'name',
                        'is_personal_team',
                    ]
                ]
            ]);
    });

    it('retorna 401 quando não está autenticado', function () {
        $this->getJson('/api/workspaces')
            ->assertUnauthorized();
    });

});

// ---------------------------------------------------------------------------
// CRIAR (store)
// ---------------------------------------------------------------------------

describe('criar workspace', function () {

    it('cria um workspace com sucesso', function () {
        $user = createUserWithPermissions(['workspaces.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/workspaces', [
                'name' => 'Meu Workspace',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Registro inserido com sucesso.');

        $this->assertDatabaseHas('workspaces', ['name' => 'Meu Workspace']);
    });

    it('retorna 422 quando o nome já existe para o mesmo usuário', function () {
        $user = createUserWithPermissions(['workspaces.create']);
        $workspaceId = $user->currentAccess->workspace_id;
        $workspace = Workspace::withoutGlobalScopes()->find($workspaceId);

        // Renomeia o workspace existente para um nome conhecido
        $workspace->update(['name' => 'Duplicado']);

        $this->withToken($user->test_token)
            ->postJson('/api/workspaces', [
                'name' => 'Duplicado',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'workspace_name_already_exists');
    });

    it('cria workspace com nome igual ao de outro usuário sem conflito', function () {
        // Usuário A tem workspace "Marketing"
        $userA = createUserWithPermissions(['workspaces.create']);
        $wsA = Workspace::withoutGlobalScopes()->find($userA->currentAccess->workspace_id);
        $wsA->update(['name' => 'Marketing']);

        // Usuário B cria workspace com o mesmo nome "Marketing"
        $userB = createUserWithPermissions(['workspaces.create']);

        $this->withToken($userB->test_token)
            ->postJson('/api/workspaces', [
                'name' => 'Marketing',
            ])
            ->assertCreated();
    });

    it('retorna 401 quando não está autenticado', function () {
        $this->postJson('/api/workspaces', ['name' => 'Qualquer'])
            ->assertUnauthorized();
    });

    it('retorna 403 quando não tem a permissão workspaces.create', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)
            ->postJson('/api/workspaces', ['name' => 'Qualquer'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// ATUALIZAR (update)
// ---------------------------------------------------------------------------

describe('atualizar workspace', function () {

    it('atualiza workspace com sucesso', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspace->uuid}", [
                'name' => 'Nome Super Atualizado',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Registro atualizado com sucesso.');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Nome Super Atualizado',
        ]);
    });

    it('retorna 404 ao tentar atualizar workspace inexistente ou sem acesso', function () {
        $user = createUserWithPermissions(['workspaces.update']);

        $this->withToken($user->test_token)
            ->putJson('/api/workspaces/uuid-falso', [
                'name' => 'Nome Super Atualizado',
            ])
            ->assertNotFound();
    });

    it('retorna 403 ao tentar atualizar workspace sem permissão', function () {
        $user = createUserWithoutPermissions();
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspace->uuid}", [
                'name' => 'Nome Super Atualizado',
            ])
            ->assertForbidden();
    });

    it('retorna 401 quando não está autenticado', function () {
        $this->putJson('/api/workspaces/uuid-qualquer', [
            'name' => 'Nome Super Atualizado',
        ])
            ->assertUnauthorized();
    });

});

// ---------------------------------------------------------------------------
// TROCAR (switch)
// ---------------------------------------------------------------------------

describe('trocar workspace', function () {
    it('troca de workspace com sucesso', function () {
        $user = createUserWithPermissions(['workspaces.view']);

        $newWorkspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Novo Workspace',
            'is_personal_team' => false,
        ]);

        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Role Switch',
            'workspace_id' => $newWorkspace->id,
        ]);

        $newAccess = Access::create([
            'user_id' => $user->id,
            'workspace_id' => $newWorkspace->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($user->test_token)
            ->postJson("/api/workspaces/{$newWorkspace->uuid}/switch")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'current_workspace' => [
                        'uuid',
                        'name',
                    ],
                    'role',
                    'permissions'
                ]
            ]);

        expect($user->fresh()->access_id)->toBe($newAccess->id);
    });

    it('retorna 404 ao tentar trocar para um workspace inexistente', function () {
        $user = createUserWithPermissions(['workspaces.view']);

        $this->withToken($user->test_token)
            ->postJson('/api/workspaces/uuid-falso/switch')
            ->assertNotFound();
    });

    it('retorna 404 ao tentar trocar para um workspace que o usuário não tem acesso', function () {
        $user = createUserWithPermissions(['workspaces.view']);
        
        $workspaceForAnotherUser = Workspace::withoutGlobalScopes()->create([
            'name' => 'Outro Workspace',
            'is_personal_team' => false,
        ]);

        $this->withToken($user->test_token)
            ->postJson("/api/workspaces/{$workspaceForAnotherUser->uuid}/switch")
            ->assertNotFound();
    });

    it('retorna 401 quando não está autenticado', function () {
        $workspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Workspace Deslogado',
            'is_personal_team' => false,
        ]);
        
        $this->postJson("/api/workspaces/{$workspace->uuid}/switch")
            ->assertUnauthorized();
    });
});


// ---------------------------------------------------------------------------
// DELETAR (destroy)
// ---------------------------------------------------------------------------

describe('deletar workspace', function () {
    it('deleta workspace com sucesso', function () {
        $user = createUserWithPermissions(['workspaces.delete'], false);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $this->withToken($user->test_token)
            ->deleteJson("/api/workspaces/{$workspace->uuid}")
            ->assertNoContent();

        expect(Workspace::withoutGlobalScopes()->find($workspace->id))->toBeNull();
    });
    it('retorna 404 ao tentar deletar workspace inexistente', function () {
        $user = createUserWithPermissions(['workspaces.delete']);

        $this->withToken($user->test_token)
            ->deleteJson('/api/workspaces/uuid-falso')
            ->assertNotFound();
    });

    it('retorna 403 ao tentar deletar workspace sem permissão', function () {
        $user = createUserWithoutPermissions();

        $workspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Workspace Sem Permissão',
            'is_personal_team' => false,
        ]);

        $this->withToken($user->test_token)
            ->deleteJson("/api/workspaces/{$workspace->uuid}")
            ->assertForbidden();
    });

    it('retorna 401 quando não está autenticado', function () {
        $workspace = Workspace::withoutGlobalScopes()->create([
            'name' => 'Workspace Deslogado',
            'is_personal_team' => false,
        ]);
        
        $this->deleteJson("/api/workspaces/{$workspace->uuid}")
            ->assertUnauthorized();
    });
});
