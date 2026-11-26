<?php

use App\Models\Access;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| PATCH /accesses/{id}
|--------------------------------------------------------------------------
|
| Antes (spec 022) o endpoint estourava TypeError → 500 (BUG-01). Corrigido:
| só role_id é aceito, o perfil tem que ser do workspace corrente e o acesso
| também (specs/002 VULN-01/VULN-02).
*/

function accessRole(int $workspaceId): Role
{
    return Role::withoutGlobalScopes()->create([
        'name' => 'Outro Perfil '.uniqid(),
        'workspace_id' => $workspaceId,
    ]);
}

describe('PATCH /accesses/{id}', function () {

    it('troca o perfil do acesso no workspace corrente', function () {
        $user = createUserWithPermissions(['accesses.update']);
        $access = $user->currentAccess;
        $otherRole = accessRole($access->workspace_id);

        $this->withToken($user->test_token)
            ->patchJson("/api/accesses/{$access->id}", ['role_id' => $otherRole->id])
            ->assertOk();

        expect(Access::find($access->id)->role_id)->toBe($otherRole->id);
    });

    it('ignora user_id e workspace_id do request', function () {
        $user = createUserWithPermissions(['accesses.update']);
        $other = createUserWithPermissions([]);
        $access = $user->currentAccess;
        $otherRole = accessRole($access->workspace_id);

        $this->withToken($user->test_token)
            ->patchJson("/api/accesses/{$access->id}", [
                'role_id' => $otherRole->id,
                'user_id' => $other->id,
                'workspace_id' => $other->currentAccess->workspace_id,
            ])
            ->assertOk();

        $fresh = Access::find($access->id);
        expect($fresh->user_id)->toBe($user->id)
            ->and($fresh->workspace_id)->toBe($access->workspace_id);
    });

    it('retorna 422 sem role_id ou com perfil de outro workspace e nada muda', function () {
        $user = createUserWithPermissions(['accesses.update']);
        $other = createUserWithPermissions([]);
        $access = $user->currentAccess;
        $foreignRole = accessRole($other->currentAccess->workspace_id);

        $this->withToken($user->test_token)
            ->patchJson("/api/accesses/{$access->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        $this->withToken($user->test_token)
            ->patchJson("/api/accesses/{$access->id}", ['role_id' => $foreignRole->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        expect(Access::find($access->id)->role_id)->toBe($access->role_id);
    });

    it('retorna 404 para acesso de outro workspace e nada muda', function () {
        $user = createUserWithPermissions(['accesses.update']);
        $other = createUserWithPermissions([]);
        $foreignAccess = $other->currentAccess;
        $ownRole = accessRole($user->currentAccess->workspace_id);

        $this->withToken($user->test_token)
            ->patchJson("/api/accesses/{$foreignAccess->id}", ['role_id' => $ownRole->id])
            ->assertNotFound();

        expect(Access::find($foreignAccess->id)->role_id)->toBe($foreignAccess->role_id);
    });

    it('retorna 403 sem a permissão accesses.update', function () {
        $user = createUserWithPermissions(['accesses.view']);
        $access = $user->currentAccess;

        $this->withToken($user->test_token)
            ->patchJson("/api/accesses/{$access->id}", ['role_id' => $access->role_id])
            ->assertForbidden();
    });
});
