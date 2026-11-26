<?php

use App\Models\Access;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| QA — corpo do 403 de workspace byte a byte (spec 022, RF-8 / Q3 / D4)
|--------------------------------------------------------------------------
|
| O HttpResponseException manual do WorkspaceController virou
| WorkspaceException::permissionDeniedInWorkspace com render() próprio.
| A spec exige o corpo idêntico ao de antes: {"message", "required_permission"},
| 403 — sem a chave "error" do render padrão do BaseException.
| Os testes existentes (WorkspaceCrudTest, WorkspacePermissionScopeTest) só
| fazem assertForbidden(); estes travam o corpo exato do caminho refatorado
| (permissão conferida no workspace da URL, não no corrente).
*/

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

/** Segundo workspace em que o usuário é só membro (sem permissões); o corrente segue o de origem. */
function workspaceWhereUserIsOnlyMember(User $user): Workspace
{
    $other = Workspace::withoutGlobalScopes()->create(['name' => 'Cliente B', 'is_personal_team' => false]);
    $role = Role::withoutGlobalScopes()->create(['name' => 'Perfil B', 'workspace_id' => $other->id]);
    Access::create(['user_id' => $user->id, 'role_id' => $role->id, 'workspace_id' => $other->id]);

    return $other;
}

describe('corpo do 403 ao agir num workspace da URL sem permissão nele', function () {

    it('update: responde exatamente {"message", "required_permission"} e não renomeia', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspaceB = workspaceWhereUserIsOnlyMember($user);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspaceB->uuid}", ['name' => 'Invadido'])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Você não tem permissão para acessar este recurso.',
                'required_permission' => 'workspaces.update',
            ]);

        expect($workspaceB->fresh()->name)->toBe('Cliente B');
    });

    it('destroy: responde exatamente {"message", "required_permission"} e não exclui', function () {
        $user = createUserWithPermissions(['workspaces.delete']);
        $workspaceB = workspaceWhereUserIsOnlyMember($user);

        $this->withToken($user->test_token)
            ->deleteJson("/api/workspaces/{$workspaceB->uuid}")
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Você não tem permissão para acessar este recurso.',
                'required_permission' => 'workspaces.delete',
            ]);

        expect(Workspace::withoutGlobalScopes()->whereKey($workspaceB->id)->exists())->toBeTrue();
    });
});
// O 404 de workspace inexistente/sem acesso já está travado em WorkspaceCrudTest.
