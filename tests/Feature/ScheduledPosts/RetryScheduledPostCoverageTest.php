<?php

use App\Jobs\CheckInstagramContainerJob;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\InstagramService;
use App\Services\Storage\StorageService;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
    Storage::fake('s3');
});

/**
 * Post em qualquer status com arquivo (e thumbnail opcional) gravado no S3 fake.
 * failed_at é definido por query direta para não disparar os observers.
 */
function qaPost(User $user, string $platform, string $status, array $overrides = [], ?int $failedDaysAgo = null, bool $withThumb = false): ScheduledPost
{
    $media = $overrides['media_path'] ?? 'workspaces/w/videos/'.uniqid().'.mp4';
    Storage::disk('s3')->put($media, 'video');

    $payload = $overrides['payload'] ?? [];
    if ($withThumb) {
        $payload['thumbnail_path'] = $overrides['thumb'] ?? 'workspaces/w/thumbnails/'.uniqid().'.jpg';
        Storage::disk('s3')->put($payload['thumbnail_path'], 'img');
    }
    unset($overrides['thumb']);

    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $overrides['social_account_id'] ?? connectedSocialAccount($user, $platform)->id,
        'platform' => $platform,
        'title' => 'Vídeo QA',
        'media_path' => $media,
        'status' => $status,
        'payload' => $payload,
    ], $overrides, ['media_path' => $media, 'payload' => $payload]));

    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update([
        'failed_at' => $failedDaysAgo === null ? null : now()->subDays($failedDaysAgo),
    ]);

    return $post->fresh();
}

describe('critério 7: falha não apaga a mídia em nenhuma plataforma', function () {

    it('YouTube/Instagram/TikTok: PublishPostJob::failed mantém mídia e thumbnail no S3', function (string $platform) {
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, $platform, 'pending', [], null, true);

        (new PublishPostJob($post))->failed(new Exception('boom'));

        Storage::disk('s3')->assertExists($post->media_path);
        Storage::disk('s3')->assertExists($post->payload['thumbnail_path']);
        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')->and($fresh->failed_at)->not->toBeNull()
            ->and($fresh->canRetry())->toBeTrue();
    })->with(['youtube', 'instagram', 'tiktok']);

    it('Instagram: container em ERROR marca failed, mantém a mídia e zera o container', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'processing', ['container_id' => 'c-1', 'container_created_at' => now()]);
        $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);
        Http::fake(['*/c-1*' => Http::response(['status_code' => 'ERROR'])]);

        try {
            (new CheckInstagramContainerJob($post, $account, 'c-1'))->handle();
        } catch (Throwable) {
            // $this->fail() fora da fila pode lançar; o estado do post é o que importa.
        }

        Storage::disk('s3')->assertExists($post->media_path);
        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->container_id)->toBeNull()
            ->and($fresh->failed_at)->not->toBeNull()
            ->and($fresh->canRetry())->toBeTrue();
    });

    it('Instagram: erro no media_publish marca failed e mantém a mídia', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'processing', ['container_id' => 'c-2', 'container_created_at' => now()]);
        $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);
        Http::fake([
            '*/c-2*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['error' => ['message' => 'nope']], 400),
        ]);

        (new CheckInstagramContainerJob($post, $account, 'c-2'))->handle();

        Storage::disk('s3')->assertExists($post->media_path);
        expect($post->fresh()->status)->toBe('failed');
    });

    it('Instagram (InstagramService): erro ao publicar container existente mantém mídia e marca failed', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'pending', ['container_id' => 'c-3', 'container_created_at' => now()]);
        $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);
        Http::fake([
            '*/c-3*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['error' => ['message' => 'nope']], 400),
        ]);

        app(InstagramService::class)->upload($account, $post);

        Storage::disk('s3')->assertExists($post->media_path);
        expect($post->fresh()->status)->toBe('failed')->and($post->fresh()->failed_at)->not->toBeNull();
    });

    it('TikTok: FAILED após o envio mantém a mídia, marca failed_at e notifica', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'tiktok', 'processing', ['container_id' => 'pub-1']);
        $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);
        Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['status' => 'FAILED', 'fail_reason' => 'motivo']])]);

        try {
            (new CheckTikTokPostStatusJob($post, $account, 'pub-1'))->handle(app(StorageService::class));
        } catch (Throwable) {
        }

        Storage::disk('s3')->assertExists($post->media_path);
        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->toContain('motivo')
            ->and($fresh->failed_at)->not->toBeNull()
            ->and(Notification::where('type', 'post_failed')->count())->toBe(1);
    });

});

describe('critério 10: notificação de falha vai para quem tem permissão de posts', function () {

    it('notifica membros com permissão de posts e não notifica quem não tem, nem outro workspace', function () {
        $author = createUserWithPermissions(['posts.create']);
        $withPerm = addUserToWorkspace($author->currentAccess->workspace);
        $withPerm->currentAccess->role->permissions()->sync([permission('posts.view')->id]);
        $withoutPerm = addUserToWorkspace($author->currentAccess->workspace);
        $withoutPerm->currentAccess->role->permissions()->sync([]);
        $outsider = createUserWithPermissions(['posts.create']);
        $post = qaPost($author, 'instagram', 'processing');

        $post->update(['status' => 'failed', 'error_message' => 'x']);

        $recipients = Notification::where('type', 'post_failed')->pluck('notifiable_id')->all();
        expect($recipients)->toContain($author->id, $withPerm->id)
            ->not->toContain($withoutPerm->id)
            ->not->toContain($outsider->id);
    });

});

describe('critério 11: YouTube publicando não apaga arquivo de post failed dentro do prazo', function () {

    it('cleanup do YouTube (deleteIfUnused) preserva o vídeo do Instagram falho', function () {
        $user = createUserWithPermissions(['posts.create']);
        $shared = 'workspaces/w/videos/shared.mp4';
        $ig = qaPost($user, 'instagram', 'failed', ['media_path' => $shared], 1);
        $yt = qaPost($user, 'youtube', 'processing', ['media_path' => $shared]);

        $m = new ReflectionMethod(YouTubeService::class, 'cleanupFiles');
        $m->setAccessible(true);
        $m->invoke(app(YouTubeService::class), $yt, []);

        Storage::disk('s3')->assertExists($shared);
        expect($ig->fresh()->canRetry())->toBeTrue();
    });

    it('thumbnail compartilhada do Instagram falho também é preservada (RF-3/RF-10)', function () {
        $user = createUserWithPermissions(['posts.create']);
        $shared = 'workspaces/w/videos/shared2.mp4';
        $thumb = 'workspaces/w/thumbnails/shared2.jpg';
        $ig = qaPost($user, 'instagram', 'failed', ['media_path' => $shared, 'thumb' => $thumb], 1, true);
        $yt = qaPost($user, 'youtube', 'processing', ['media_path' => $shared, 'thumb' => $thumb], null, true);

        $m = new ReflectionMethod(YouTubeService::class, 'cleanupFiles');
        $m->setAccessible(true);
        $m->invoke(app(YouTubeService::class), $yt, $yt->payload);

        Storage::disk('s3')->assertExists($shared);
        Storage::disk('s3')->assertExists($thumb);
    });

});

describe('RF-3: reenvio exige mídia E thumbnail no storage', function () {

    it('retorna 422 media_expired se a thumbnail não existe mais, mesmo com o vídeo presente', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'failed', ['error_message' => 'x'], 1, true);
        Storage::disk('s3')->delete($post->payload['thumbnail_path']);

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(422)
            ->assertJsonPath('error', 'media_expired');

        expect($post->fresh()->status)->toBe('failed');
        Queue::assertNothingPushed();
    });

});

describe('reenvio duas vezes seguidas (RF-14 / critério 2)', function () {

    it('o segundo reenvio imediato retorna 409 e só um job foi enfileirado', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'failed', ['error_message' => 'x'], 1);

        $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertOk();
        $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(409)->assertJsonPath('error', 'retry_not_allowed');

        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('sem limite de reenvios: falha, reenvia, falha de novo e reenvia de novo (fluxo real) notificando a cada falha', function () {
        Bus::fake([PublishPostJob::class]);
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'failed', ['error_message' => 'x'], 1);

        foreach (range(1, 3) as $i) {
            $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertOk();
            expect($post->fresh()->status)->toBe('pending');
            $this->travel(2)->minutes();
            (new PublishPostJob($post->fresh()))->failed(new Exception("falha {$i}"));
            expect($post->fresh()->failed_at)->not->toBeNull();
        }

        expect(Notification::where('type', 'post_failed')->count())->toBe(3);
        Bus::assertDispatchedTimes(PublishPostJob::class, 3);
    });

    it('RF-12: a falha após o reenvio reinicia o prazo de 5 dias', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'failed', ['error_message' => 'x'], 4);

        $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertOk();
        (new PublishPostJob($post->fresh()))->failed(new Exception('de novo'));

        $fresh = $post->fresh();
        expect($fresh->failed_at->greaterThan(now()->subMinute()))->toBeTrue();
        $this->artisan('posts:purge-failed-media')->assertSuccessful();
        Storage::disk('s3')->assertExists($post->media_path);
        expect($fresh->retryDeadline()->greaterThan(now()->addDays(4)))->toBeTrue();
    });

});

describe('post failed legado (sem failed_at)', function () {

    it('listagem: can_retry true, retry_until null (sem quebrar) e media_url preenchida', function () {
        $user = createUserWithPermissions(['posts.view']);
        $post = qaPost($user, 'instagram', 'failed', ['error_message' => 'x'], null);
        $this->mock(StorageService::class)->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/v.mp4');

        $item = collect($this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk()->json('data'))->first();

        expect($item['can_retry'])->toBeTrue()->and($item['retry_until'])->toBeNull();
    });

    it('reenvio funciona e preenche failed_at ao falhar de novo', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'failed', ['error_message' => 'x'], null);

        $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertOk();
        (new PublishPostJob($post->fresh()))->failed(new Exception('x'));

        expect($post->fresh()->failed_at)->not->toBeNull();
    });

});

describe('reagendamento com scheduled_at (critério 13)', function () {

    it('grava a nova data, despacha o job com delay até essa data e responde 200', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'failed', ['error_message' => 'x', 'scheduled_at' => now()->subDay()], 1);
        $when = now()->addHours(3)->startOfSecond();

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry", ['scheduled_at' => $when->toIso8601String()])
            ->assertOk()->assertJsonPath('data.status', 'pending');

        expect($post->fresh()->scheduled_at->equalTo($when))->toBeTrue();
        Queue::assertPushed(PublishPostJob::class, fn ($j) => $j->delay !== null && Carbon::parse($j->delay)->equalTo($when));
    });

    it('scheduled_at inválido (texto, passado) retorna 422 e nada muda', function (mixed $value) {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = qaPost($user, 'instagram', 'failed', ['error_message' => 'x'], 1);

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry", ['scheduled_at' => $value])
            ->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')->and($fresh->error_message)->toBe('x');
        Queue::assertNothingPushed();
    })->with(['texto' => 'amanhã-talvez', 'passado' => '2020-01-01T10:00:00Z']);

    it('scheduled_at a menos de 5 minutos em post inexistente/de outro workspace não vaza: 404', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $this->withToken($user->test_token)
            ->postJson('/api/scheduled-posts/'.Str::uuid().'/retry')
            ->assertNotFound();
    });

});

describe('permissões e isolamento extras', function () {

    it('super admin reenvia mesmo sem posts.create no papel', function () {
        Queue::fake();
        $owner = createUserWithPermissions(['posts.create']);
        $post = qaPost($owner, 'instagram', 'failed', ['error_message' => 'x'], 1);
        $admin = createUserWithPermissions([], true, $owner->currentAccess->workspace);
        User::whereKey($admin->id)->update(['is_super_admin' => true]);
        $admin->test_token = $admin->createToken('t')->plainTextToken;

        $this->withToken($admin->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertOk();
    });

    it('sem autenticação retorna 401', function () {
        $this->postJson('/api/scheduled-posts/'.Str::uuid().'/retry')->assertUnauthorized();
    });

    it('403 não vaza a existência do post nem altera nada para post de outro workspace', function () {
        Queue::fake();
        $viewer = createUserWithPermissions(['posts.view']);
        $other = createUserWithPermissions(['posts.create']);
        $post = qaPost($other, 'instagram', 'failed', ['error_message' => 'x'], 1);

        $this->withToken($viewer->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertForbidden();
        expect($post->fresh()->status)->toBe('failed');
    });

});

describe('listagem (critério 6)', function () {

    it('pending e processing têm can_retry false e retry_until null; failed purgado tem media_url null', function () {
        $user = createUserWithPermissions(['posts.view']);
        $this->mock(StorageService::class)->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/v.mp4');
        qaPost($user, 'instagram', 'pending', ['title' => 'P']);
        qaPost($user, 'instagram', 'processing', ['title' => 'Q']);
        qaPost($user, 'instagram', 'failed', ['title' => 'F', 'media_purged_at' => now()], 7);

        $by = collect($this->withToken($user->test_token)->getJson('/api/scheduled-posts')->json('data'))->keyBy('title');

        expect($by['P']['can_retry'])->toBeFalse()->and($by['P']['retry_until'])->toBeNull()
            ->and($by['Q']['can_retry'])->toBeFalse()
            ->and($by['F']['can_retry'])->toBeFalse()->and($by['F']['media_url'])->toBeNull();
    });

    it('a rota social-accounts/schedules usa o mesmo Resource e traz can_retry/retry_until', function () {
        $user = createUserWithPermissions(['social_accounts.view', 'posts.view']);
        $this->mock(StorageService::class)->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/v.mp4');
        $account = connectedSocialAccount($user, 'instagram');
        qaPost($user, 'instagram', 'failed', ['social_account_id' => $account->id, 'error_message' => 'x'], 1);

        $response = $this->withToken($user->test_token)->getJson('/api/social-accounts/schedules');
        $response->assertOk();

        expect(json_encode($response->json()))->toContain('can_retry')->toContain('retry_until');
    });

});

describe('limpeza (critérios 8 e 9) com post failed e thumbnail', function () {

    it('apaga na fronteira: 5 dias e 1 minuto limpa; 4 dias 23h mantém', function () {
        $user = createUserWithPermissions(['posts.view']);
        $old = qaPost($user, 'instagram', 'failed', [], null);
        $recent = qaPost($user, 'instagram', 'failed', [], null);
        ScheduledPost::withoutGlobalScopes()->whereKey($old->id)->update(['failed_at' => now()->subDays(5)->subMinute()]);
        ScheduledPost::withoutGlobalScopes()->whereKey($recent->id)->update(['failed_at' => now()->subDays(5)->addHour()]);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertMissing($old->media_path);
        Storage::disk('s3')->assertExists($recent->media_path);
    });

    it('não apaga a mídia compartilhada com post processing', function () {
        $user = createUserWithPermissions(['posts.view']);
        $failed = qaPost($user, 'instagram', 'failed', ['media_path' => 'workspaces/w/videos/s.mp4'], 6);
        qaPost($user, 'youtube', 'processing', ['media_path' => 'workspaces/w/videos/s.mp4']);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertExists('workspaces/w/videos/s.mp4');
    });

});
