<?php

use App\Exceptions\AnalyticsApiException;
use App\Exceptions\AnalyticsException;
use App\Exceptions\BaseException;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Segurança — auditoria do diff da refatoração 022
|--------------------------------------------------------------------------
|
| Regressões ativas: proteções que a refatoração moveu de lugar (controller →
| service/exception) e que hoje dependem de detalhe de implementação — estes
| testes impedem que um próximo refactor as derrube.
|
| VULN-AV-01: achado introduzido pela task 1.3 e corrigido na própria 022
| (permissão no authorize() dos FormRequests do avatar) — regressão ativa
| no fim do arquivo.
*/

// ---------------------------------------------------------------------------
// BUG-01 corrigido: PATCH /users/{uuid} grava só campos validados (VULN-01)
// ---------------------------------------------------------------------------

describe('regressão BUG-01: PATCH /users/{uuid} não aceita mass assignment', function () {

    it('atualiza só o nome e ignora is_super_admin, access_id e email', function () {
        $user = createUserWithPermissions(['users.update']);
        $originalEmail = $user->email;
        $originalAccessId = $user->access_id;

        $this->withToken($user->test_token)
            ->patchJson("/api/users/{$user->uuid}", [
                'name' => 'Nome Novo',
                'is_super_admin' => true,
                'access_id' => 999999,
                'email' => 'injetado@exemplo.com',
            ])
            ->assertOk();

        $fresh = User::find($user->id);
        expect($fresh->name)->toBe('Nome Novo')
            ->and((bool) $fresh->is_super_admin)->toBeFalse()
            ->and($fresh->access_id)->toBe($originalAccessId)
            ->and($fresh->email)->toBe($originalEmail);
    });
});

// ---------------------------------------------------------------------------
// RF-8: isolamento do authorizePermissionInWorkspace movido para o service
// ---------------------------------------------------------------------------
// O corpo exato do 403 (membro sem permissão no workspace da URL) já está
// travado em Workspaces/WorkspacePermissionDeniedBodyTest — não duplicado aqui.

describe('regressão 022: 403 de workspace tipado (WorkspaceException::render)', function () {

    it('não-membro: update e destroy de workspace alheio respondem 404 e nada muda', function () {
        $attacker = createUserWithPermissions(['workspaces.update', 'workspaces.delete']);
        $victim = createUserWithPermissions([]);
        $victimWorkspace = Workspace::withoutGlobalScopes()->find($victim->currentAccess->workspace_id);

        $this->withToken($attacker->test_token)
            ->putJson("/api/workspaces/{$victimWorkspace->uuid}", ['name' => 'Tomado'])
            ->assertNotFound();

        $this->withToken($attacker->test_token)
            ->deleteJson("/api/workspaces/{$victimWorkspace->uuid}")
            ->assertNotFound();

        $fresh = Workspace::withoutGlobalScopes()->find($victimWorkspace->id);
        expect($fresh)->not->toBeNull()
            ->and($fresh->name)->toBe($victimWorkspace->name);
    });
});

// ---------------------------------------------------------------------------
// D6: AnalyticsApiException precisa ficar FORA da hierarquia de AnalyticsException
// ---------------------------------------------------------------------------

describe('regressão 022: AnalyticsApiException fora da hierarquia de AnalyticsException', function () {

    it('não é subclasse de AnalyticsException (senão o corpo de terceiro chega ao cliente)', function () {
        // AnalyticsService::callProvider relança AnalyticsException direto ao cliente;
        // a mensagem de AnalyticsApiException carrega status + body da API de terceiro
        // e só pode cair no ramo genérico (log + 502 providerUnavailable).
        expect(is_subclass_of(AnalyticsApiException::class, AnalyticsException::class))->toBeFalse()
            ->and(is_subclass_of(AnalyticsApiException::class, BaseException::class))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// VULN-AV-01 — task 1.3: a validação do avatar roda antes do guard de permissão
// ---------------------------------------------------------------------------

describe('VULN-AV-01: confirmar avatar não deve consultar o storage antes da permissão', function () {

    // Se a WorkspaceAvatarPathRule rodar antes da permissão, membro sem workspaces.update
    // dispara a checagem de existência no S3 e a diferença 422 ("arquivo não encontrado")
    // × 403 vira um oráculo de existência de arquivo em workspaces/{uuid}/avatar/, além de
    // custo S3 sem autorização. Comportamento seguro: 403 nos dois casos, sem tocar o storage.
    it('membro sem workspaces.update recebe 403 independente de o arquivo existir', function (bool $fileExists) {
        $user = createUserWithPermissions(['workspaces.view']);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $storage = $this->partialMock(StorageService::class);
        $storage->shouldReceive('exists')->never()->andReturn($fileExists);

        $this->withToken($user->test_token)
            ->putJson("/api/workspaces/{$workspace->uuid}/avatar", [
                'path' => "workspaces/{$workspace->uuid}/avatar/sonda.jpg",
            ])
            ->assertStatus(403);
    })->with([
        'arquivo existe' => [true],
        'arquivo não existe' => [false],
    ]);
});
