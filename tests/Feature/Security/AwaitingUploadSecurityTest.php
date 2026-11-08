<?php

use App\Events\ScheduledPostEvent;
use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// Auditoria de segurança da spec 021 (reserva com media_pending + upload-complete).

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
    Carbon::setTestNow(Carbon::now()->startOfSecond());
    Queue::fake([PublishPostJob::class]);
    Storage::fake('s3');
    $this->user = createUserWithPermissions(['posts.create', 'posts.view', 'posts.delete']);
    $this->ws = $this->user->currentAccess->workspace->uuid;
});

afterEach(fn () => Carbon::setTestNow());

function secAwPath(User $user, string $name = 'a.mp4', string $dir = 'videos'): string
{
    return 'workspaces/'.$user->currentAccess->workspace->uuid."/{$dir}/{$name}";
}

function secAwReserve(User $user, string $path, array $body = []): TestResponse
{
    $account = connectedSocialAccount($user, 'youtube');

    return test()->withToken($user->test_token)->postJson('/api/scheduled-posts', array_merge([
        'social_account_uuids' => [$account->uuid],
        'title' => 'Título',
        'media_storage_path' => $path,
        'media_pending' => true,
        'scheduled_at' => now()->addMinutes(6)->toIso8601String(),
    ], $body));
}

function secAwPost(User $user, string $path, array $overrides = []): ScheduledPost
{
    return ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => connectedSocialAccount($user, 'youtube')->id,
        'platform' => 'youtube',
        'title' => 'Reservado',
        'media_path' => $path,
        'payload' => [],
        'scheduled_at' => now()->addMinutes(5),
        'status' => 'awaiting_upload',
    ], $overrides));
}

function secAwComplete(User $user, mixed $path): TestResponse
{
    return test()->withToken($user->test_token)->postJson('/api/scheduled-posts/upload-complete', ['media_storage_path' => $path]);
}

describe('spec 021: isolamento de tenant', function () {

    it('regressão: upload-complete com path do workspace alheio dá 422, não libera nem despacha', function () {
        $victim = createUserWithPermissions(['posts.create']);
        $path = secAwPath($victim);
        Storage::disk('s3')->put($path, 'video');
        $post = secAwPost($victim, $path);

        secAwComplete($this->user, $path)->assertStatus(422);

        expect($post->fresh()->status)->toBe('awaiting_upload');
        Queue::assertNothingPushed();
    });

    it('regressão: reservar (media_pending) com path de outro workspace dá 422 e não cria post', function () {
        $victim = createUserWithPermissions(['posts.create']);

        secAwReserve($this->user, secAwPath($victim))->assertStatus(422)->assertJsonValidationErrors('media_storage_path');

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('regressão: prefixo parecido (sem a barra) não passa pelo StoragePathRule', function () {
        secAwReserve($this->user, "workspaces/{$this->ws}x/videos/a.mp4")->assertStatus(422);
        secAwReserve($this->user, "workspaces/{$this->ws}/videosx/a.mp4")->assertStatus(422);
        secAwReserve($this->user, "workspaces/{$this->ws}/avatar/a.mp4")->assertStatus(422);
        secAwReserve($this->user, secAwPath($this->user, 'a.jpg', 'thumbnails'))->assertStatus(422);
    });

    it('regressão: cancel de awaiting_upload de outro workspace dá 404 e a mídia/status não mudam', function () {
        $victim = createUserWithPermissions(['posts.create']);
        $path = secAwPath($victim);
        Storage::disk('s3')->put($path, 'video');
        $post = secAwPost($victim, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertNotFound();

        expect($post->fresh()->status)->toBe('awaiting_upload');
        Storage::disk('s3')->assertExists($path);
    });

    it('VULN-UP-02: path com ".." dentro do prefixo do workspace é recusado na reserva', function () {
        $victim = createUserWithPermissions(['posts.create']);
        $vws = $victim->currentAccess->workspace->uuid;

        $r = secAwReserve($this->user, "workspaces/{$this->ws}/videos/../../{$vws}/videos/alheio.mp4");

        $r->assertStatus(422);
        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('regressão: reserva com extensão não aceita (.html, sem extensão) é recusada pelo PayloadBuilder', function () {
        secAwReserve($this->user, secAwPath($this->user, 'x.html'))->assertStatus(422);
        secAwReserve($this->user, secAwPath($this->user, 'x'))->assertStatus(422);
    });
});

describe('spec 021: mass assignment e validação de entrada', function () {

    it('regressão: status, upload_completed_at, user_id, workspace_id e id do request são ignorados', function () {
        $other = createUserWithPermissions(['posts.create']);

        $r = secAwReserve($this->user, secAwPath($this->user), [
            'status' => 'published',
            'upload_completed_at' => now()->subDay()->toIso8601String(),
            'user_id' => $other->id,
            'workspace_id' => $other->currentAccess->workspace_id,
            'id' => 9999,
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'error_message' => 'x',
            'media_path' => secAwPath($other),
        ]);

        $r->assertCreated();
        $post = ScheduledPost::withoutGlobalScopes()->first();
        expect($post->status)->toBe('awaiting_upload')
            ->and($post->upload_completed_at)->toBeNull()
            ->and($post->user_id)->toBe($this->user->id)
            ->and($post->uuid)->not->toBe('11111111-1111-4111-8111-111111111111')
            ->and($post->media_path)->toBe(secAwPath($this->user))
            ->and($post->error_message)->toBeNull();
    });

    it('regressão: media_pending não booleano dá 422', function () {
        secAwReserve($this->user, secAwPath($this->user), ['media_pending' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('media_pending');
        secAwReserve($this->user, secAwPath($this->user), ['media_pending' => ['x']])->assertStatus(422);
    });

    it('regressão: media_pending ausente exige o arquivo no S3 (fluxo antigo intacto)', function () {
        $account = connectedSocialAccount($this->user, 'youtube');

        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', [
            'social_account_uuids' => [$account->uuid],
            'title' => 'T',
            'media_storage_path' => secAwPath($this->user, 'nao-existe.mp4'),
            'media_pending' => false,
        ])->assertStatus(422);
    });

    it('regressão: upload-complete com path ausente, array, número ou gigante dá 422, nunca 500', function () {
        foreach ([null, ['a'], 123, str_repeat('a', 5000), "workspaces/{$this->ws}/videos/".str_repeat('a', 5000)] as $bad) {
            $status = secAwComplete($this->user, $bad)->status();
            expect($status)->toBeIn([404, 422], 'valor: '.json_encode($bad));
        }
    });
});

describe('spec 021: confirmação forjada', function () {

    it('regressão: upload-complete sem arquivo no S3 dá 422 e o post segue aguardando', function () {
        $path = secAwPath($this->user);
        $post = secAwPost($this->user, $path);

        secAwComplete($this->user, $path)->assertStatus(422);

        expect($post->fresh()->status)->toBe('awaiting_upload');
        Queue::assertNothingPushed();
    });

    it('VULN-UP-04: arquivo vazio (0 bytes) não confirma o upload', function () {
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, '');
        $post = secAwPost($this->user, $path);

        secAwComplete($this->user, $path)->assertStatus(422);

        expect($post->fresh()->status)->toBe('awaiting_upload');
    });

    it('VULN-UP-05: com vários posts no mesmo path, a thumbnail de todos é conferida antes de liberar', function () {
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, 'video');
        $semThumb = secAwPost($this->user, $path);
        $thumbInexistente = secAwPost($this->user, $path, ['payload' => ['thumbnail_path' => secAwPath($this->user, 'fantasma.jpg', 'thumbnails')]]);

        secAwComplete($this->user, $path);

        expect($thumbInexistente->fresh()->status)->toBe('awaiting_upload');
    });

    it('regressão: repetir upload-complete não despacha job duplicado nem reabre post cancelado', function () {
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, 'video');
        $post = secAwPost($this->user, $path);

        secAwComplete($this->user, $path)->assertOk();
        secAwComplete($this->user, $path)->assertOk();
        Queue::assertPushed(PublishPostJob::class, 1);

        $post->update(['status' => 'cancelled']);
        secAwComplete($this->user, $path)->assertOk();
        expect($post->fresh()->status)->toBe('cancelled');
        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('regressão: post expirado (failed) não é reaberto por upload-complete', function () {
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, 'video');
        $post = secAwPost($this->user, $path, ['status' => 'failed', 'error_message' => 'fixo']);

        secAwComplete($this->user, $path)->assertStatus(409);

        expect($post->fresh()->status)->toBe('failed');
        Queue::assertNothingPushed();
    });
});

describe('spec 021: permissões', function () {

    it('regressão: upload-complete exige posts.create (403 só com posts.view)', function () {
        $viewer = createUserWithPermissions(['posts.view']);
        $path = secAwPath($viewer);
        Storage::disk('s3')->put($path, 'video');
        $post = secAwPost($viewer, $path);

        secAwComplete($viewer, $path)->assertForbidden();

        expect($post->fresh()->status)->toBe('awaiting_upload');
        Queue::assertNothingPushed();
    });

    it('regressão: cancel de awaiting_upload exige posts.delete', function () {
        $creator = createUserWithPermissions(['posts.create']);
        $post = secAwPost($creator, secAwPath($creator));

        $this->withToken($creator->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertForbidden();

        expect($post->fresh()->status)->toBe('awaiting_upload');
    });

    it('regressão: toda rota do ScheduledPostController tem permission:* ou está no mapa', function () {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->getActionName(), 'ScheduledPostController'));

        expect($routes)->not->toBeEmpty();
        foreach ($routes as $route) {
            $mw = implode(',', $route->gatherMiddleware());
            expect(str_contains($mw, 'permission:'))->toBeTrue($route->uri());
        }
    });

    it('regressão: as rotas de escrita ficam atrás de throttle (45/min por usuário)', function () {
        $route = Route::getRoutes()->match(app('request')->create('/api/scheduled-posts/upload-complete', 'POST'));

        expect(implode(',', $route->gatherMiddleware()))->toContain('throttle:45,1');
    });
});

describe('spec 021: vazamento, evento e notificação', function () {

    it('regressão: payload do evento tem só uuid e status, no canal privado do workspace', function () {
        $post = secAwPost($this->user, secAwPath($this->user), ['title' => 'segredo', 'caption' => 'privado']);
        $event = new ScheduledPostEvent($post);

        expect($event->broadcastWith())->toBe(['uuid' => $post->uuid, 'status' => 'awaiting_upload'])
            ->and($event->broadcastAs())->toBe('ScheduledPostUpdated')
            ->and($event->broadcastOn()[0]->name)->toBe("private-workspaces.{$this->ws}");
    });

    it('regressão: o canal workspaces.{uuid} só autoriza quem tem acesso ao workspace', function () {
        $stranger = createUserWithPermissions(['posts.view']);

        expect($this->user->hasAccessToWorkspace($this->ws))->toBeTrue()
            ->and($stranger->hasAccessToWorkspace($this->ws))->toBeFalse();
    });

    it('regressão: o evento sai ao liberar, mas não é emitido para posts de outro workspace', function () {
        Event::fake([ScheduledPostEvent::class]);
        $victim = createUserWithPermissions(['posts.create']);
        $path = secAwPath($victim);
        Storage::disk('s3')->put($path, 'video');
        secAwPost($victim, $path);

        secAwComplete($this->user, $path)->assertStatus(422);

        Event::assertNotDispatched(ScheduledPostEvent::class);
    });

    it('regressão: notificação post_upload_late vai só ao autor, mesmo se outro membro confirmar', function () {
        $author = createUserWithPermissions(['posts.create'], true, $this->user->currentAccess->workspace);
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, 'video');
        secAwPost($author, $path, ['scheduled_at' => now()->subMinutes(10)]);

        secAwComplete($this->user, $path)->assertOk()->assertJsonPath('late', true);

        expect(Notification::query()->where('notifiable_id', $author->id)->count())->toBe(1)
            ->and(Notification::query()->where('notifiable_id', $this->user->id)->count())->toBe(0);
    });

    it('regressão: erro de domínio do upload-complete não vaza path, bucket nem stack', function () {
        config(['app.debug' => false]);
        $path = secAwPath($this->user);
        secAwPost($this->user, $path);

        $r = secAwComplete($this->user, $path)->assertStatus(422);

        expect($r->getContent())->not->toContain('s3')->not->toContain('Exception')->not->toContain($path);
    });

    it('regressão: falha do storage em upload-complete não expõe a mensagem crua', function () {
        config(['app.debug' => false]);
        $path = secAwPath($this->user);
        secAwPost($this->user, $path);
        $this->mock(StorageService::class, function ($m) {
            $m->shouldReceive('pathBelongsToWorkspace')->andReturn(true);
            $m->shouldReceive('exists')->andThrow(new RuntimeException('AWS secret bucket-interno-xyz'));
        });

        $r = secAwComplete($this->user, $path);

        expect($r->getContent())->not->toContain('bucket-interno-xyz')->not->toContain('AWS secret');
    });
});

describe('spec 021: deleteIfUnused', function () {

    it('regressão: cancelar um awaiting_upload com o path de um post pending de outro membro não apaga a mídia dele', function () {
        $member = createUserWithPermissions(['posts.create', 'posts.delete'], true, $this->user->currentAccess->workspace);
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, 'video');
        secAwPost($this->user, $path, ['status' => 'pending']);
        $reserva = secAwPost($member, $path);

        $this->withToken($member->test_token)->deleteJson("/api/scheduled-posts/{$reserva->uuid}")->assertSuccessful();

        Storage::disk('s3')->assertExists($path);
    });

    it('regressão: um awaiting_upload segura a mídia (não é apagada ao cancelar o post irmão)', function () {
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, 'video');
        $a = secAwPost($this->user, $path);
        secAwPost($this->user, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$a->uuid}")->assertSuccessful();

        Storage::disk('s3')->assertExists($path);
    });

    it('regressão: cancelar o último awaiting_upload apaga a mídia e cancel repetido dá 4xx sem mudar nada', function () {
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, 'video');
        $a = secAwPost($this->user, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$a->uuid}")->assertSuccessful();
        Storage::disk('s3')->assertMissing($path);
        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$a->uuid}")->assertStatus(409);
    });
});

describe('spec 021: antecedência mínima (achado de negócio)', function () {

    it('caracterização: reservar com +5 min e confirmar depois do horário publica na hora, sem janela de cancelamento prévia', function () {
        $path = secAwPath($this->user);
        Storage::disk('s3')->put($path, 'video');
        secAwReserve($this->user, $path, ['scheduled_at' => now()->addMinutes(6)->toIso8601String()])->assertCreated();
        Queue::assertNothingPushed();

        Carbon::setTestNow(now()->addMinutes(30));
        secAwComplete($this->user, $path)->assertOk()->assertJsonPath('late', true);

        // Intencional pela spec (RF-2): o job vai sem delay. A regra dos N minutos vale só para o clique de reserva.
        Queue::assertPushed(PublishPostJob::class, fn ($job) => $job->delay === null || $job->delay <= now());
    });

    it('regressão: reserva sem a antecedência mínima dá 422', function () {
        secAwReserve($this->user, secAwPath($this->user), ['scheduled_at' => now()->addMinute()->toIso8601String()])->assertStatus(422);
        secAwReserve($this->user, secAwPath($this->user), ['scheduled_at' => now()->subHour()->toIso8601String()])->assertStatus(422);
    });
});
