<?php

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| QA — ordem 403×422 no avatar do workspace (spec 022, task 1.3)
|--------------------------------------------------------------------------
|
| A permissão no workspace da URL é checada no authorize() dos FormRequests,
| antes das regras: sem permissão + corpo inválido responde 403, como antes
| da refatoração (a inversão para 422 foi revertida junto com o VULN-AV-01).
| Cada cenário isolado (só sem permissão / só corpo inválido) segue coberto
| em WorkspaceAvatarTest.
*/

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function avatarWorkspaceOf($user): Workspace
{
    return Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);
}

describe('sem permissão E corpo inválido: a permissão responde primeiro (403)', function () {

    it('upload-url responde 403, não 422', function () {
        $user = createUserWithPermissions(['workspaces.view']);
        $workspace = avatarWorkspaceOf($user);

        $this->withToken($user->test_token)
            ->postJson("/api/workspaces/{$workspace->uuid}/avatar/upload-url", ['content_type' => 'video/mp4'])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Você não tem permissão para acessar este recurso.',
                'required_permission' => 'workspaces.update',
            ]);
    });

    it('confirmar avatar responde 403, não 422, e não grava nada', function () {
        $user = createUserWithPermissions(['workspaces.view']);
        $workspace = avatarWorkspaceOf($user);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspace->uuid}/avatar", [])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Você não tem permissão para acessar este recurso.',
                'required_permission' => 'workspaces.update',
            ]);

        expect($workspace->fresh()->avatar_path)->toBeNull();
    });
});
