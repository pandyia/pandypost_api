<?php

use App\Events\WorkspaceEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    // Parcial: a lógica de path roda de verdade; só o que toca o S3 é simulado por teste.
    $this->storage = $this->partialMock(StorageService::class);
});

function adminWorkspace(User $user): Workspace
{
    return Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);
}

describe('pedir URL de upload', function () {

    it('devolve a URL pré-assinada com o path do próprio workspace', function (string $contentType, string $extension) {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = adminWorkspace($user);

        $response = $this->withToken($user->test_token)
            ->postJson("/api/workspaces/{$workspace->uuid}/avatar/upload-url", ['content_type' => $contentType])
            ->assertOk()
            ->assertJsonStructure(['url', 'headers', 'path', 'content_type', 'max_size', 'expires_in'])
            ->assertJsonPath('max_size', 5242880);

        expect($response->json('path'))->toStartWith("workspaces/{$workspace->uuid}/avatar/")->toEndWith(".{$extension}");
    })->with([
        'jpeg' => ['image/jpeg', 'jpg'],
        'png' => ['image/png', 'png'],
        'webp' => ['image/webp', 'webp'],
    ]);

    it('rejeita tipo que não é imagem permitida', function (mixed $payload) {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = adminWorkspace($user);

        $this->withToken($user->test_token)->postJson("/api/workspaces/{$workspace->uuid}/avatar/upload-url", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('content_type');
    })->with([
        'ausente' => [[]],
        'vídeo' => [['content_type' => 'video/mp4']],
        'gif' => [['content_type' => 'image/gif']],
    ]);

    it('exige autenticação', function () {
        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Qualquer', 'is_personal_team' => false]);

        $this->postJson("/api/workspaces/{$workspace->uuid}/avatar/upload-url", ['content_type' => 'image/jpeg'])->assertUnauthorized();
    });

    it('nega sem workspaces.update', function () {
        $user = createUserWithPermissions(['workspaces.view']);
        $workspace = adminWorkspace($user);

        $this->withToken($user->test_token)->postJson("/api/workspaces/{$workspace->uuid}/avatar/upload-url", ['content_type' => 'image/jpeg'])
            ->assertForbidden()->assertJsonPath('required_permission', 'workspaces.update');
    });

    it('admin do A, só membro do B, não mexe na foto do B', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspaceB = memberOfOtherWorkspace($user);

        $this->withToken($user->test_token)->postJson("/api/workspaces/{$workspaceB->uuid}/avatar/upload-url", ['content_type' => 'image/jpeg'])
            ->assertForbidden();
        $this->withToken($user->test_token)->deleteJson("/api/workspaces/{$workspaceB->uuid}/avatar")->assertForbidden();
    });

    it('quem tem a permissão no B (mesmo com outro workspace corrente) pode pedir a URL do B', function () {
        $user = createUserWithPermissions([]);
        $workspaceB = memberOfOtherWorkspace($user, ['workspaces.update']);

        $this->withToken($user->test_token)->postJson("/api/workspaces/{$workspaceB->uuid}/avatar/upload-url", ['content_type' => 'image/png'])
            ->assertOk();
    });

    it('não-membro recebe 404', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $stranger = Workspace::withoutGlobalScopes()->create(['name' => 'Alheio', 'is_personal_team' => false]);

        $this->withToken($user->test_token)->postJson("/api/workspaces/{$stranger->uuid}/avatar/upload-url", ['content_type' => 'image/png'])
            ->assertNotFound();
    });
});

describe('confirmar a foto enviada', function () {

    it('grava a foto, apaga a antiga, avisa os membros e devolve a URL assinada', function () {
        Event::fake([WorkspaceEvent::class]);
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = adminWorkspace($user);
        $old = "workspaces/{$workspace->uuid}/avatar/antiga.jpg";
        $new = "workspaces/{$workspace->uuid}/avatar/nova.jpg";
        Workspace::withoutGlobalScopes()->whereKey($workspace->id)->update(['avatar_path' => $old]);

        $this->storage->shouldReceive('exists')->with($new)->andReturnTrue();
        $this->storage->shouldReceive('mimeType')->with($new)->andReturn('image/jpeg');
        $this->storage->shouldReceive('size')->with($new)->andReturn(150_000);
        $this->storage->shouldReceive('delete')->once()->with($old)->andReturnTrue();
        $this->storage->shouldReceive('generateDownloadUrl')->with($new, 518400)->andReturn('https://storage.exemplo.test/ws?sig=1');

        $this->withToken($user->test_token)->putJson("/api/workspaces/{$workspace->uuid}/avatar", ['path' => $new])
            ->assertOk()
            ->assertJsonPath('data.uuid', $workspace->uuid)
            ->assertJsonPath('data.avatar', 'https://storage.exemplo.test/ws?sig=1')
            ->assertJsonMissingPath('data.avatar_path');

        expect(Workspace::withoutGlobalScopes()->find($workspace->id)->avatar_path)->toBe($new);
        Event::assertDispatched(WorkspaceEvent::class, fn (WorkspaceEvent $e) => $e->workspace->is($workspace)
            && $e->broadcastAs() === 'WorkspaceUpdated'
            && $e->broadcastOn()[0]->name === 'private-workspaces.'.$workspace->uuid);
    });

    it('workspace pessoal também pode ter foto', function () {
        $user = createUserWithPermissions(['workspaces.update'], isPersonalTeam: true);
        $workspace = adminWorkspace($user);
        $new = "workspaces/{$workspace->uuid}/avatar/p.png";

        $this->storage->shouldReceive('exists')->with($new)->andReturnTrue();
        $this->storage->shouldReceive('mimeType')->with($new)->andReturn('image/png');
        $this->storage->shouldReceive('size')->with($new)->andReturn(1_000);
        $this->storage->shouldReceive('generateDownloadUrl')->andReturn('https://storage.exemplo.test/p');

        expect($workspace->is_personal_team)->toBeTrue();
        $this->withToken($user->test_token)->putJson("/api/workspaces/{$workspace->uuid}/avatar", ['path' => $new])->assertOk();
    });

    it('rejeita path de outro workspace', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = adminWorkspace($user);

        $this->withToken($user->test_token)->putJson("/api/workspaces/{$workspace->uuid}/avatar", ['path' => 'workspaces/outro/avatar/x.jpg'])
            ->assertUnprocessable()->assertJsonValidationErrors('path');
    });

    it('rejeita arquivo que não é imagem e apaga o que subiu', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = adminWorkspace($user);
        $path = "workspaces/{$workspace->uuid}/avatar/x.jpg";

        $this->storage->shouldReceive('exists')->with($path)->andReturnTrue();
        $this->storage->shouldReceive('mimeType')->with($path)->andReturn('video/mp4');
        $this->storage->shouldReceive('delete')->once()->with($path)->andReturnTrue();

        $this->withToken($user->test_token)->putJson("/api/workspaces/{$workspace->uuid}/avatar", ['path' => $path])
            ->assertUnprocessable()->assertJsonPath('error', 'workspace_invalid_avatar_file');
    });

    it('rejeita imagem maior que o limite e apaga o que subiu', function () {
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = adminWorkspace($user);
        $path = "workspaces/{$workspace->uuid}/avatar/grande.jpg";

        $this->storage->shouldReceive('exists')->with($path)->andReturnTrue();
        $this->storage->shouldReceive('mimeType')->with($path)->andReturn('image/jpeg');
        $this->storage->shouldReceive('size')->with($path)->andReturn(6_000_000);
        $this->storage->shouldReceive('delete')->once()->with($path)->andReturnTrue();

        $this->withToken($user->test_token)->putJson("/api/workspaces/{$workspace->uuid}/avatar", ['path' => $path])
            ->assertUnprocessable()->assertJsonPath('error', 'workspace_avatar_too_large');
    });
});

describe('remover a foto', function () {

    it('apaga o arquivo, zera a foto e avisa os membros', function () {
        Event::fake([WorkspaceEvent::class]);
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = adminWorkspace($user);
        $path = "workspaces/{$workspace->uuid}/avatar/a.jpg";
        Workspace::withoutGlobalScopes()->whereKey($workspace->id)->update(['avatar_path' => $path]);

        $this->storage->shouldReceive('delete')->once()->with($path)->andReturnTrue();

        $this->withToken($user->test_token)->deleteJson("/api/workspaces/{$workspace->uuid}/avatar")
            ->assertOk()->assertJsonPath('data.avatar', null);

        expect(Workspace::withoutGlobalScopes()->find($workspace->id)->avatar_path)->toBeNull();
        Event::assertDispatched(WorkspaceEvent::class);
    });

    it('é idempotente: sem foto responde 200 e não emite evento', function () {
        Event::fake([WorkspaceEvent::class]);
        $user = createUserWithPermissions(['workspaces.update']);
        $workspace = adminWorkspace($user);

        $this->withToken($user->test_token)->deleteJson("/api/workspaces/{$workspace->uuid}/avatar")->assertOk();

        Event::assertNotDispatched(WorkspaceEvent::class);
    });
});

describe('a foto sai nas respostas que mostram o workspace', function () {

    it('aparece em /me, na lista e na troca de workspace, sem expor o path', function () {
        $user = createUserWithPermissions(['workspaces.view']);
        $workspace = adminWorkspace($user);
        $path = "workspaces/{$workspace->uuid}/avatar/a.jpg";
        Workspace::withoutGlobalScopes()->whereKey($workspace->id)->update(['avatar_path' => $path]);
        $this->storage->shouldReceive('generateDownloadUrl')->with($path, 518400)->andReturn('https://storage.exemplo.test/assinada');

        $this->withToken($user->test_token)->getJson('/api/me')
            ->assertOk()->assertJsonPath('data.current_workspace.avatar', 'https://storage.exemplo.test/assinada');

        $list = $this->withToken($user->test_token)->getJson('/api/workspaces')->assertOk();
        expect(collect($list->json('data'))->firstWhere('uuid', $workspace->uuid)['avatar'])->toBe('https://storage.exemplo.test/assinada');
        expect(json_encode($list->json()))->not->toContain('avatar_path');

        $this->withToken($user->test_token)->postJson("/api/workspaces/{$workspace->uuid}/switch")
            ->assertOk()->assertJsonPath('data.current_workspace.avatar', 'https://storage.exemplo.test/assinada');
    });

    it('é nulo quando o workspace não tem foto', function () {
        $user = createUserWithPermissions([]);

        $this->withToken($user->test_token)->getJson('/api/me')->assertJsonPath('data.current_workspace.avatar', null);
    });
});
