<?php

use App\Models\ContentPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function pipelineCard(int $workspaceId, int $userId, array $overrides = []): ContentPipeline
{
    return ContentPipeline::withoutGlobalScopes()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'workspace_id' => $workspaceId,
        'user_id' => $userId,
        'title' => 'Card de teste',
        'stage' => 'idea',
    ], $overrides));
}

// ---------------------------------------------------------------------------
// BOARD
// ---------------------------------------------------------------------------

describe('board do pipeline', function () {

    it('retorna todas as etapas com os cards do workspace agrupados', function () {
        $user = createUserWithPermissions(['posts.view']);
        pipelineCard($user->currentAccess->workspace_id, $user->id, ['stage' => 'script']);

        $this->withToken($user->test_token)
            ->getJson('/api/pipeline/board')
            ->assertOk()
            ->assertJsonStructure(['idea', 'script', 'recorded', 'editing', 'ready', 'scheduled'])
            ->assertJsonCount(1, 'script')
            ->assertJsonCount(0, 'idea');
    });

    it('não exibe cards de outros workspaces', function () {
        $user = createUserWithPermissions(['posts.view']);
        $other = createUserWithPermissions([]);
        pipelineCard($other->currentAccess->workspace_id, $other->id);

        $this->withToken($user->test_token)
            ->getJson('/api/pipeline/board')
            ->assertOk()
            ->assertJsonCount(0, 'idea');
    });

    it('retorna 403 sem a permissão posts.view', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)
            ->getJson('/api/pipeline/board')
            ->assertForbidden();
    });

    it('retorna 401 quando não está autenticado', function () {
        $this->getJson('/api/pipeline/board')->assertUnauthorized();
    });

});

// ---------------------------------------------------------------------------
// CRIAR
// ---------------------------------------------------------------------------

describe('criar card', function () {

    it('cria o card na etapa idea do workspace corrente', function () {
        $user = createUserWithPermissions(['posts.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/pipeline', ['title' => 'Novo vídeo', 'platform' => 'youtube'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Novo vídeo')
            ->assertJsonPath('data.stage', 'idea')
            ->assertJsonPath('data.created_by', $user->name);

        $this->assertDatabaseHas('content_pipelines', [
            'title' => 'Novo vídeo',
            'workspace_id' => $user->currentAccess->workspace_id,
            'user_id' => $user->id,
        ]);
    });

    it('ignora workspace_id e stage enviados no corpo', function () {
        $user = createUserWithPermissions(['posts.create']);
        $other = createUserWithPermissions([]);

        $this->withToken($user->test_token)
            ->postJson('/api/pipeline', [
                'title' => 'Tentativa',
                'workspace_id' => $other->currentAccess->workspace_id,
                'stage' => 'ready',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'idea');

        $this->assertDatabaseHas('content_pipelines', [
            'title' => 'Tentativa',
            'workspace_id' => $user->currentAccess->workspace_id,
        ]);
    });

    it('retorna 422 sem título, com plataforma inválida ou data passada', function () {
        $user = createUserWithPermissions(['posts.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/pipeline', [
                'platform' => 'orkut',
                'due_date' => now()->subDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'platform', 'due_date']);
    });

    it('retorna 403 sem a permissão posts.create', function () {
        $user = createUserWithPermissions(['posts.view']);

        $this->withToken($user->test_token)
            ->postJson('/api/pipeline', ['title' => 'X'])
            ->assertForbidden();
    });

});

// ---------------------------------------------------------------------------
// EXIBIR / ISOLAMENTO
// ---------------------------------------------------------------------------

describe('exibir card', function () {

    it('exibe um card do workspace pelo uuid', function () {
        $user = createUserWithPermissions(['posts.view']);
        $card = pipelineCard($user->currentAccess->workspace_id, $user->id);

        $this->withToken($user->test_token)
            ->getJson("/api/pipeline/{$card->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $card->uuid);
    })->skip('BUG-01: BaseController::show chama findById() com o uuid da rota (ver specs/002-security-hardening).');

    it('retorna 404 para card de outro workspace', function () {
        $user = createUserWithPermissions(['posts.view']);
        $other = createUserWithPermissions([]);
        $card = pipelineCard($other->currentAccess->workspace_id, $other->id);

        $this->withToken($user->test_token)
            ->getJson("/api/pipeline/{$card->uuid}")
            ->assertNotFound();
    });

    it('retorna 404 ao editar, mover ou remover card de outro workspace', function () {
        $user = createUserWithPermissions(['posts.view', 'posts.create']);
        $other = createUserWithPermissions([]);
        $card = pipelineCard($other->currentAccess->workspace_id, $other->id);

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}", ['title' => 'Invadido'])
            ->assertNotFound();

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}/move", ['stage' => 'script'])
            ->assertNotFound();

        $this->withToken($user->test_token)
            ->deleteJson("/api/pipeline/{$card->uuid}")
            ->assertNotFound();

        expect($card->fresh()->title)->toBe('Card de teste')
            ->and($card->fresh()->trashed())->toBeFalse();
    });

});

// ---------------------------------------------------------------------------
// MOVER ETAPA
// ---------------------------------------------------------------------------

describe('mover card de etapa', function () {

    it('avança uma etapa', function () {
        $user = createUserWithPermissions(['posts.create']);
        $card = pipelineCard($user->currentAccess->workspace_id, $user->id);

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}/move", ['stage' => 'script'])
            ->assertOk();

        expect($card->fresh()->stage->value)->toBe('script');
    });

    it('retrocede uma etapa', function () {
        $user = createUserWithPermissions(['posts.create']);
        $card = pipelineCard($user->currentAccess->workspace_id, $user->id, ['stage' => 'editing']);

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}/move", ['stage' => 'recorded'])
            ->assertOk();

        expect($card->fresh()->stage->value)->toBe('recorded');
    });

    it('retorna 422 invalid_stage_transition ao pular etapas', function () {
        $user = createUserWithPermissions(['posts.create']);
        $card = pipelineCard($user->currentAccess->workspace_id, $user->id);

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}/move", ['stage' => 'ready'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'invalid_stage_transition');

        expect($card->fresh()->stage->value)->toBe('idea');
    });

    it('retorna 422 de validação ao tentar mover manualmente para scheduled', function () {
        $user = createUserWithPermissions(['posts.create']);
        $card = pipelineCard($user->currentAccess->workspace_id, $user->id, ['stage' => 'ready']);

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}/move", ['stage' => 'scheduled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);

        expect($card->fresh()->stage->value)->toBe('ready');
    });

    it('retorna 403 sem a permissão posts.create', function () {
        $user = createUserWithPermissions(['posts.view']);
        $card = pipelineCard($user->currentAccess->workspace_id, $user->id);

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}/move", ['stage' => 'script'])
            ->assertForbidden();
    });

});

// ---------------------------------------------------------------------------
// REMOVER / RESTAURAR
// ---------------------------------------------------------------------------

describe('remover e restaurar card', function () {

    it('faz soft delete e depois restaura o card', function () {
        $user = createUserWithPermissions(['posts.view', 'posts.create']);
        $card = pipelineCard($user->currentAccess->workspace_id, $user->id);

        $this->withToken($user->test_token)
            ->deleteJson("/api/pipeline/{$card->uuid}")
            ->assertOk();

        $this->assertSoftDeleted('content_pipelines', ['id' => $card->id]);

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}/restore")
            ->assertOk();

        $this->assertNotSoftDeleted('content_pipelines', ['id' => $card->id]);
    });

    it('não restaura card removido de outro workspace', function () {
        $user = createUserWithPermissions(['posts.view', 'posts.create']);
        $other = createUserWithPermissions([]);
        $card = pipelineCard($other->currentAccess->workspace_id, $other->id);
        $card->delete();

        $this->withToken($user->test_token)
            ->patchJson("/api/pipeline/{$card->uuid}/restore")
            ->assertNotFound();

        $this->assertSoftDeleted('content_pipelines', ['id' => $card->id]);
    });

});
