<?php

use App\Models\Access;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

/**
 * Cria um segundo workspace em que o usuário é membro com um perfil que tem (ou não) as permissões.
 * O workspace corrente do usuário continua sendo o de origem.
 */
function memberOfOtherWorkspace(User $user, array $permissionNames = []): Workspace
{
    $other = Workspace::withoutGlobalScopes()->create(['name' => 'Cliente B', 'is_personal_team' => false]);
    $role = Role::withoutGlobalScopes()->create(['name' => 'Perfil B', 'workspace_id' => $other->id]);

    foreach ($permissionNames as $name) {
        permission($name);
    }
    $role->permissions()->sync(Permission::whereIn('name', $permissionNames)->pluck('id'));

    Access::create(['user_id' => $user->id, 'role_id' => $role->id, 'workspace_id' => $other->id]);

    return $other;
}

describe('editar/excluir workspace pela URL exige permissão NAQUELE workspace', function () {

    it('admin do A, sem permissão no B (só membro), não renomeia o B', function () {
        $user = createUserWithPermissions(['workspaces.update', 'workspaces.delete', 'workspaces.view']);
        $workspaceB = memberOfOtherWorkspace($user);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspaceB->uuid}", ['name' => 'Renomeado pelo A'])
            ->assertForbidden();

        expect($workspaceB->fresh()->name)->toBe('Cliente B');
    });

    it('admin do A, sem permissão no B, não exclui o B', function () {
        $user = createUserWithPermissions(['workspaces.update', 'workspaces.delete', 'workspaces.view']);
        $workspaceB = memberOfOtherWorkspace($user);

        $this->withToken($user->test_token)
            ->deleteJson("/api/workspaces/{$workspaceB->uuid}")
            ->assertForbidden();

        expect(Workspace::withoutGlobalScopes()->whereKey($workspaceB->id)->exists())->toBeTrue();
    });

    it('continua renomeando o próprio workspace corrente', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspaceA = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspaceA->uuid}", ['name' => 'Novo nome'])
            ->assertOk();

        expect($workspaceA->fresh()->name)->toBe('Novo nome');
    });

    it('com permissão no B e o B como workspace corrente, renomeia o B', function () {
        $user = createUserWithPermissions([]);
        $workspaceB = memberOfOtherWorkspace($user, ['workspaces.update']);
        $accessB = Access::where('user_id', $user->id)->where('workspace_id', $workspaceB->id)->first();
        User::whereKey($user->id)->update(['access_id' => $accessB->id]);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspaceB->uuid}", ['name' => 'B renomeado'])
            ->assertOk();

        expect($workspaceB->fresh()->name)->toBe('B renomeado');
    });
});
