<?php

use App\Models\Access;
use App\Models\ContentPipeline;
use App\Models\Role;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Testes de segurança — vulnerabilidades conhecidas
|--------------------------------------------------------------------------
| Cada teste descreve o comportamento SEGURO esperado. Os que ainda falham
| contra o código atual estão marcados com ->skip('VULN-NN: ...') e
| documentados em specs/002-security-hardening/spec.md. Ao corrigir a
| vulnerabilidade, remova o ->skip() — o teste passa a proteger a correção.
*/

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function foreignWorkspace(string $name = 'Workspace Alheio'): Workspace
{
    return Workspace::withoutGlobalScopes()->create(['name' => $name, 'is_personal_team' => false]);
}

describe('regressão: permissão vale só no workspace corrente', function () {

    it('nega acesso quando a permissão existe apenas em outro workspace do usuário', function () {
        // Hoje a proteção vem do global scope de Role aplicado dentro do
        // whereHas('role.permissions') em User::hasPermission() — é incidental,
        // por isso este teste existe: impede que um refactor a remova.
        // Permissão roles.view no workspace A...
        $user = createUserWithPermissions(['roles.view']);

        // ...mas o workspace corrente passa a ser B, onde o perfil não tem permissão alguma.
        $workspaceB = foreignWorkspace();
        $roleB = Role::withoutGlobalScopes()->create(['name' => 'Sem permissões', 'workspace_id' => $workspaceB->id]);
        $accessB = Access::create(['user_id' => $user->id, 'role_id' => $roleB->id, 'workspace_id' => $workspaceB->id]);
        User::whereKey($user->id)->update(['access_id' => $accessB->id]);

        $this->withToken($user->test_token)
            ->getJson('/api/roles')
            ->assertForbidden();
    });

});

describe('VULN-01: criação de usuário não pode elevar privilégio', function () {

    it('ignora is_super_admin enviado no POST /users', function () {
        $user = createUserWithPermissions(['users.create']);

        $this->withToken($user->test_token)->postJson('/api/users', [
            'name' => 'Atacante',
            'email' => 'atacante@example.com',
            'password' => 'Senha12345',
            'is_super_admin' => true,
        ]);

        expect(User::withoutGlobalScopes()->where('email', 'atacante@example.com')->value('is_super_admin'))
            ->not->toBeTrue();
    });

    it('cria o usuário só com nome, email e senha', function () {
        $user = createUserWithPermissions(['users.create']);

        $this->withToken($user->test_token)->postJson('/api/users', [
            'name' => 'Novo',
            'email' => 'novo@example.com',
            'password' => 'Senha12345',
            'access_id' => $user->currentAccess->id,
        ])->assertCreated();

        $created = User::withoutGlobalScopes()->where('email', 'novo@example.com')->first();
        expect($created->access_id)->toBeNull()
            ->and($created->is_super_admin)->toBeFalse()
            ->and(Hash::check('Senha12345', $created->password))->toBeTrue();
    });

    it('retorna 422 sem os campos obrigatórios', function () {
        $user = createUserWithPermissions(['users.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

});

describe('VULN-02: acessos (accesses) isolados por workspace', function () {

    it('não lista acessos de outros workspaces', function () {
        $user = createUserWithPermissions(['accesses.view']);
        $other = createUserWithPermissions([]);

        $response = $this->withToken($user->test_token)->getJson('/api/accesses')->assertOk();

        $workspaceIds = collect($response->json('data'))->pluck('workspace_id')->unique()->values();
        expect($workspaceIds->all())->not->toContain($other->currentAccess->workspace_id);
    });

    it('não permite criar acesso em um workspace alheio', function () {
        $user = createUserWithPermissions(['accesses.create']);
        $victim = createUserWithPermissions([]);
        $victimRoleId = $victim->currentAccess->role_id;
        $victimWorkspaceId = $victim->currentAccess->workspace_id;

        $this->withToken($user->test_token)->postJson('/api/accesses', [
            'user_id' => $user->id,
            'role_id' => $victimRoleId,
            'workspace_id' => $victimWorkspaceId,
        ]);

        expect(Access::where('user_id', $user->id)->where('workspace_id', $victimWorkspaceId)->exists())
            ->toBeFalse();
    });

});

describe('VULN-02: acessos — leitura, remoção e criação no workspace corrente', function () {

    it('retorna 404 ao exibir ou remover acesso de outro workspace', function () {
        $user = createUserWithPermissions(['accesses.view', 'accesses.delete']);
        $foreignAccess = createUserWithPermissions([])->currentAccess;

        $this->withToken($user->test_token)
            ->getJson("/api/accesses/{$foreignAccess->id}")
            ->assertNotFound();

        $this->withToken($user->test_token)
            ->deleteJson("/api/accesses/{$foreignAccess->id}")
            ->assertNotFound();

        expect(Access::whereKey($foreignAccess->id)->exists())->toBeTrue();
    });

    it('cria acesso no workspace corrente mesmo se outro workspace_id for enviado', function () {
        $user = createUserWithPermissions(['accesses.create']);
        $workspaceId = $user->currentAccess->workspace_id;
        $role = Role::withoutGlobalScopes()->create(['name' => 'Editor', 'workspace_id' => $workspaceId]);
        $newMember = User::factory()->create();
        $foreignWorkspace = foreignWorkspace();

        $this->withToken($user->test_token)
            ->postJson('/api/accesses', [
                'user_id' => $newMember->id,
                'role_id' => $role->id,
                'workspace_id' => $foreignWorkspace->id,
            ])
            ->assertCreated();

        expect(Access::where('user_id', $newMember->id)->value('workspace_id'))->toBe($workspaceId)
            ->and(Access::where('workspace_id', $foreignWorkspace->id)->exists())->toBeFalse();
    });

    it('retorna 422 quando o perfil não é do workspace corrente', function () {
        $user = createUserWithPermissions(['accesses.create']);
        $foreignRoleId = createUserWithPermissions([])->currentAccess->role_id;

        $this->withToken($user->test_token)
            ->postJson('/api/accesses', ['user_id' => $user->id, 'role_id' => $foreignRoleId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);
    });

    it('retorna 403 sem a permissão accesses.create', function () {
        $user = createUserWithPermissions(['accesses.view']);

        $this->withToken($user->test_token)
            ->postJson('/api/accesses', [])
            ->assertForbidden();
    });

});

describe('VULN-03: posts agendados isolados por workspace', function () {

    it('não lista posts agendados de outros workspaces', function () {
        $user = createUserWithPermissions(['posts.view']);
        $other = createUserWithPermissions([]);

        $account = SocialAccount::withoutGlobalScopes()->create([
            'user_id' => $other->id,
            'workspace_id' => $other->currentAccess->workspace_id,
            'platform' => 'youtube',
            'platform_id' => 'canal-alheio',
            'access_token' => 'token-falso',
            'nickname' => 'Canal alheio',
        ]);

        ScheduledPost::create([
            'user_id' => $other->id,
            'social_account_id' => $account->id,
            'platform' => 'youtube',
            'media_path' => 'workspaces/x/videos/a.mp4',
            'title' => 'Post secreto de outro workspace',
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this->withToken($user->test_token)
            ->getJson('/api/scheduled-posts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('continua listando os posts do próprio workspace', function () {
        $user = createUserWithPermissions(['posts.view']);

        $account = SocialAccount::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'workspace_id' => $user->currentAccess->workspace_id,
            'platform' => 'youtube',
            'platform_id' => 'meu-canal',
            'access_token' => 'token-falso',
            'nickname' => 'Meu canal',
        ]);

        ScheduledPost::create([
            'user_id' => $user->id,
            'social_account_id' => $account->id,
            'platform' => 'youtube',
            'media_path' => 'workspaces/x/videos/b.mp4',
            'title' => 'Meu post',
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this->withToken($user->test_token)
            ->getJson('/api/scheduled-posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Meu post');
    });

});

describe('VULN-04: pipeline exige permissão para editar e remover', function () {

    beforeEach(function () {
        $this->member = createUserWithPermissions(['posts.view']);
        $this->card = ContentPipeline::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->member->currentAccess->workspace_id,
            'user_id' => $this->member->id,
            'title' => 'Card',
            'stage' => 'idea',
        ]);
    });

    it('retorna 403 ao editar card sem permissão de escrita', function () {
        $this->withToken($this->member->test_token)
            ->patchJson("/api/pipeline/{$this->card->uuid}", ['title' => 'Alterado'])
            ->assertForbidden();
    })->skip('VULN-04: update/destroy/restore do ContentPipelineController não estão em $permissionMethods.');

    it('retorna 403 ao remover card sem permissão de escrita', function () {
        $this->withToken($this->member->test_token)
            ->deleteJson("/api/pipeline/{$this->card->uuid}")
            ->assertForbidden();
    })->skip('VULN-04: update/destroy/restore do ContentPipelineController não estão em $permissionMethods.');

});

describe('VULN-05: workspace criado pelo usuário não pode se declarar pessoal', function () {

    it('ignora is_personal_team no POST /workspaces', function () {
        $user = createUserWithPermissions(['workspaces.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/workspaces', ['name' => 'Falso Pessoal', 'is_personal_team' => true])
            ->assertCreated();

        expect(Workspace::withoutGlobalScopes()->where('name', 'Falso Pessoal')->value('is_personal_team'))
            ->toBeFalse();
    });

    it('ignora uuid e is_personal_team no PUT /workspaces/{uuid}', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);
        $workspace->update(['is_personal_team' => false]);
        $originalUuid = $workspace->uuid;

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$originalUuid}", [
                'name' => 'Renomeado',
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'is_personal_team' => true,
            ])
            ->assertOk();

        $fresh = $workspace->fresh();
        expect($fresh->name)->toBe('Renomeado')
            ->and($fresh->uuid)->toBe($originalUuid)
            ->and($fresh->is_personal_team)->toBeFalse();
    });

    it('retorna 422 sem nome ao criar ou atualizar workspace', function () {
        $user = createUserWithPermissions(['workspaces.create', 'workspaces.update']);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $this->withToken($user->test_token)
            ->postJson('/api/workspaces', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspace->uuid}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

});

describe('BUG-01: show/update genéricos do BaseController', function () {

    it('exibe um usuário do workspace pelo uuid', function () {
        $user = createUserWithPermissions(['users.view']);

        $this->withToken($user->test_token)
            ->getJson("/api/users/{$user->uuid}")
            ->assertOk();
    })->skip('BUG-01: BaseController::show chama findById() com o uuid da rota.');

    it('exibe um workspace do usuário pelo uuid', function () {
        $user = createUserWithPermissions(['workspaces.view']);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $this->withToken($user->test_token)
            ->getJson("/api/workspaces/{$workspace->uuid}")
            ->assertOk();
    })->skip('BUG-01: BaseController::show chama findById() com o uuid da rota.');

    it('atualiza um usuário do workspace pelo uuid', function () {
        $user = createUserWithPermissions(['users.update']);

        $this->withToken($user->test_token)
            ->patchJson("/api/users/{$user->uuid}", ['name' => 'Novo Nome'])
            ->assertOk();
    })->skip('BUG-01: BaseController::update chama BaseService::update(array, id) com assinatura invertida (TypeError).');

});

describe('ADM-01: catálogo de planos é exclusivo do super admin', function () {

    beforeEach(function () {
        config(['services.stripe.enabled' => false]);
    });

    it('retorna 403 para dono de workspace com billing.manage', function () {
        $owner = createUserWithPermissions(['billing.view', 'billing.manage']);

        $this->withToken($owner->test_token)
            ->postJson('/api/admin/plans', ['name' => 'Plano pirata'])
            ->assertForbidden();

        $this->withToken($owner->test_token)
            ->getJson('/api/admin/plans')
            ->assertForbidden();

        $this->assertDatabaseMissing('plans', ['name' => 'Plano pirata']);
    });

    it('permite ao super admin gerenciar planos', function () {
        $admin = createSuperAdmin();

        $this->withToken($admin->test_token)
            ->postJson('/api/admin/plans', ['name' => 'Pro'])
            ->assertCreated();
    });

});
