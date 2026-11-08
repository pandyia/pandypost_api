<?php

use App\Enums\Exceptions\ScheduledPostError;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\TikTokService;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
    Storage::fake('s3');
});

// Helpers com prefixo próprio: este arquivo roda sozinho (--filter / caminho) sem depender de StuckPostsTest.

function stk020Post(User $user, string $platform, int $minutesAgo, array $overrides = []): ScheduledPost
{
    $media = $overrides['media_path'] ?? 'workspaces/w/videos/'.uniqid().'.mp4';
    Storage::disk('s3')->put($media, 'video');

    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => connectedSocialAccount($user, $platform)->id,
        'platform' => $platform,
        'title' => 'Vídeo preso',
        'status' => 'processing',
        'payload' => [],
    ], $overrides, ['media_path' => $media]));

    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['updated_at' => now()->subMinutes($minutesAgo)]);

    return $post->fresh();
}

function stk020Row(ScheduledPost $post): ScheduledPost
{
    return ScheduledPost::withoutGlobalScopes()->findOrFail($post->id);
}

function stk020TikTokStatus(string $status, array $extra = []): void
{
    Http::fake(['open.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response([
        'data' => array_merge(['status' => $status], $extra), 'error' => ['code' => 'ok', 'message' => '', 'log_id' => 'l'],
    ])]);
}

function stk020FailedNotifications(): int
{
    return Notification::where('type', 'like', '%failed%')->count();
}

describe('critérios 1, 3 e 4: limites, status e notificação única', function () {

    it('TikTok 31 min sem publish_id vira failed com texto pt-BR "conferir na conta", failed_at e uma só notificação em duas execuções', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        $p31 = stk020Post($user, 'tiktok', 31);
        $p29 = stk020Post($user, 'tiktok', 29);

        $this->artisan('posts:close-stuck')->assertSuccessful();
        $failedAt = stk020Row($p31)->failed_at;
        $this->artisan('posts:close-stuck')->assertSuccessful();

        expect(stk020Row($p31)->status)->toBe('failed')
            ->and(stk020Row($p31)->error_message)->toBe(ScheduledPostError::UNVERIFIABLE->message())
            ->and(stk020Row($p31)->error_message)->toContain('Confira')
            ->and($failedAt)->not->toBeNull()
            ->and(stk020Row($p31)->failed_at->equalTo($failedAt))->toBeTrue()
            ->and(stk020Row($p29)->status)->toBe('processing')
            ->and(stk020FailedNotifications())->toBe(1);
        Http::assertNothingSent();
    });

    it('YouTube: 29 min não muda, 31 min sem ID fecha', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        $p29 = stk020Post($user, 'youtube', 29);
        $p31 = stk020Post($user, 'youtube', 31);

        $this->artisan('posts:close-stuck');

        expect(stk020Row($p29)->status)->toBe('processing')->and(stk020Row($p31)->status)->toBe('failed');
    });

    it('Instagram: 59 min não muda, 61 min sem container fecha como failed', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        $p59 = stk020Post($user, 'instagram', 59);
        $p61 = stk020Post($user, 'instagram', 61);

        $this->artisan('posts:close-stuck');

        expect(stk020Row($p59)->status)->toBe('processing')
            ->and(stk020Row($p61)->status)->toBe('failed')
            ->and(stk020Row($p61)->error_message)->toBe(ScheduledPostError::UNVERIFIABLE->message());
    });

    it('failed, published, pending e cancelled antigos não são alterados nem consultados (nenhum campo muda)', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        $posts = collect(['pending', 'published', 'cancelled'])->map(fn ($s) => stk020Post($user, 'instagram', 9999, ['status' => $s, 'container_id' => 'c-1']));
        $failed = stk020Post($user, 'tiktok', 9999, ['status' => 'failed', 'error_message' => 'Erro comum', 'container_id' => 'p-1', 'failed_at' => now()->subDays(2)]);
        $before = $posts->push($failed)->map(fn ($p) => stk020Row($p)->only(['status', 'error_message', 'updated_at']));

        $this->artisan('posts:close-stuck');

        $posts->each(fn ($p, $i) => expect(stk020Row($p)->only(['status', 'error_message', 'updated_at']))->toEqual($before[$i]));
        Http::assertNothingSent();
    });

    it('o comando está agendado a cada 5 minutos', function () {
        Artisan::call('schedule:list');

        expect(Artisan::output())->toContain('posts:close-stuck');
    });
});

describe('RF-3 e segurança: rede indisponível e mensagens', function () {

    it('erro/5xx da rede na conferência não fecha o post antes de 2x o limite e não vaza token no log', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['*' => Http::response(['error' => 'token=SEGREDO-123'], 500)]);
        Log::spy();
        $post = stk020Post($user, 'tiktok', 40, ['container_id' => 'pub-1']);

        $this->artisan('posts:close-stuck')->assertSuccessful();

        expect(stk020Row($post)->status)->toBe('processing');
        Log::shouldNotHaveReceived('warning', fn ($msg) => is_string($msg) && str_contains($msg, 'SEGREDO-123'));
    });

    it('rede fora do ar além de 2x o limite fecha com texto fixo STUCK_TIMEOUT', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['*' => Http::response('boom', 500)]);
        $post = stk020Post($user, 'tiktok', 70, ['container_id' => 'pub-1']);

        $this->artisan('posts:close-stuck');

        expect(stk020Row($post)->status)->toBe('failed')
            ->and(stk020Row($post)->error_message)->toBe(ScheduledPostError::STUCK_TIMEOUT->message());
    });

    it('fail_reason/status do TikTok e erro do container Instagram com texto de terceiro nunca chegam ao error_message', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake([
            'open.tiktokapis.com/*' => Http::response(['data' => ['status' => 'FAILED', 'fail_reason' => 'https://evil.test/?access_token=TOK-ABC'], 'error' => ['code' => 'ok']]),
            '*/c-ig*' => Http::response(['status_code' => 'ERROR', 'status' => 'Bearer TOK-XYZ https://graph.test/x']),
        ]);
        $tt = stk020Post($user, 'tiktok', 40, ['container_id' => 'pub-1']);
        $ig = stk020Post($user, 'instagram', 70, ['container_id' => 'c-ig']);

        $this->artisan('posts:close-stuck');

        foreach ([$tt, $ig] as $post) {
            $msg = stk020Row($post)->error_message;
            expect(stk020Row($post)->status)->toBe('failed')
                ->and($msg)->not->toContain('TOK-')->not->toContain('http')->not->toContain('Bearer');
        }
    });

    it('Instagram com container FINISHED que ninguém publicou vira failed (nada foi ao ar)', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['*/c-1*' => Http::response(['status_code' => 'FINISHED'])]);
        $post = stk020Post($user, 'instagram', 70, ['container_id' => 'c-1']);

        $this->artisan('posts:close-stuck');

        expect(stk020Row($post)->status)->toBe('failed');
    });
});

describe('critério 8: três redes, uma falha, mídia compartilhada', function () {

    it('falha do Instagram não afeta TikTok/YouTube publicados e a mídia compartilhada permanece para o reenvio', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake([
            'open.tiktokapis.com/*' => Http::response(['data' => ['status' => 'PUBLISH_COMPLETE'], 'error' => ['code' => 'ok']]),
            '*/c-ig*' => Http::response(['status_code' => 'ERROR']),
        ]);
        $shared = 'workspaces/w/videos/shared.mp4';
        $tt = stk020Post($user, 'tiktok', 40, ['container_id' => 'pub-1', 'media_path' => $shared]);
        $ig = stk020Post($user, 'instagram', 70, ['container_id' => 'c-ig', 'media_path' => $shared]);
        $yt = stk020Post($user, 'youtube', 40, ['platform_post_id' => 'yt-1', 'media_path' => $shared]);

        $this->artisan('posts:close-stuck');

        expect(stk020Row($tt)->status)->toBe('published')
            ->and(stk020Row($yt)->status)->toBe('published')
            ->and(stk020Row($ig)->status)->toBe('failed')
            ->and(stk020Row($ig)->media_path)->toBe($shared);
        Storage::disk('s3')->assertExists($shared); // o failed ainda precisa do arquivo (retenção 5 dias)
    });

    it('com a mídia compartilhada, o reenvio do que falhou reaproveita o arquivo sem novo upload', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['*/c-ig*' => Http::response(['status_code' => 'ERROR'])]);
        $shared = 'workspaces/w/videos/shared.mp4';
        $ig = stk020Post($user, 'instagram', 70, ['container_id' => 'c-ig', 'media_path' => $shared]);
        stk020Post($user, 'tiktok', 5, ['status' => 'published', 'media_path' => $shared]);

        $this->artisan('posts:close-stuck');
        Http::fake(['*/c-ig*' => Http::response(['status_code' => 'ERROR'])]);

        $this->actingAs($user)->postJson("/api/scheduled-posts/{$ig->uuid}/retry")->assertSuccessful();

        expect(stk020Row($ig)->status)->toBe('pending')->and(stk020Row($ig)->container_id)->toBeNull();
        Storage::disk('s3')->assertExists($shared);
        Bus::assertDispatched(PublishPostJob::class);
    });

    it('quando todos publicam, a mídia é apagada só depois do último', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['status' => 'PUBLISH_COMPLETE'], 'error' => ['code' => 'ok']])]);
        $shared = 'workspaces/w/videos/shared.mp4';
        $tt = stk020Post($user, 'tiktok', 40, ['container_id' => 'pub-1', 'media_path' => $shared]);
        $yt = stk020Post($user, 'youtube', 40, ['platform_post_id' => 'yt-1', 'media_path' => $shared]);
        stk020Post($user, 'instagram', 5, ['status' => 'processing', 'media_path' => $shared]); // ainda em curso, não é "preso"

        $this->artisan('posts:close-stuck');

        expect(stk020Row($tt)->status)->toBe('published')->and(stk020Row($yt)->status)->toBe('published');
        Storage::disk('s3')->assertExists($shared);
    });
});

describe('idempotência', function () {

    it('rodar duas vezes um post publicado pela rede não muda published_at nem consulta de novo', function () {
        $user = createUserWithPermissions(['posts.create']);
        stk020TikTokStatus('PUBLISH_COMPLETE');
        $post = stk020Post($user, 'tiktok', 40, ['container_id' => 'pub-1']);

        $this->artisan('posts:close-stuck');
        $publishedAt = stk020Row($post)->published_at;
        Http::fake();
        $this->artisan('posts:close-stuck');

        expect(stk020Row($post)->status)->toBe('published')->and(stk020Row($post)->published_at->equalTo($publishedAt))->toBeTrue();
        Http::assertNothingSent();
    });

    it('post fechado como failed e reenviado (pending) não é promovido nem reaberto pelo comando', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        stk020TikTokStatus('FAILED', ['fail_reason' => 'x']);
        $post = stk020Post($user, 'tiktok', 40, ['container_id' => 'pub-1']);
        $this->artisan('posts:close-stuck');
        $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertSuccessful();

        stk020TikTokStatus('PUBLISH_COMPLETE');
        $this->artisan('posts:close-stuck');

        expect(stk020Row($post)->status)->toBe('pending');
    });

    it('promoção tardia notifica falha apenas na primeira vez e não cria nova falha', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        stk020Post($user, 'youtube', 40);
        $this->artisan('posts:close-stuck');
        $this->artisan('posts:close-stuck');
        $this->artisan('posts:close-stuck');

        expect(stk020FailedNotifications())->toBe(1);
    });
});

describe('/retry: isolamento, 403 e não duplicar', function () {

    it('outro workspace recebe 404, nada muda e a rede nem é consultada', function () {
        Bus::fake();
        $owner = createUserWithPermissions(['posts.create']);
        $other = createUserWithPermissions(['posts.create']);
        Http::fake();
        $post = stk020Post($owner, 'tiktok', 5, ['status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now(), 'error_message' => 'x']);
        $before = stk020Row($post)->only(['status', 'container_id', 'error_message']);

        $this->actingAs($other)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertNotFound();

        expect(stk020Row($post)->only(['status', 'container_id', 'error_message']))->toEqual($before);
        Http::assertNothingSent();
        Bus::assertNotDispatched(PublishPostJob::class);
    });

    it('sem a permissão posts.create devolve 403 sem consultar a rede', function () {
        $owner = createUserWithPermissions(['posts.create']);
        $viewer = createUserWithPermissions([], workspace: $owner->currentAccess->workspace);
        Http::fake();
        $post = stk020Post($owner, 'tiktok', 5, ['status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()]);

        $this->actingAs($viewer)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertForbidden();

        expect(stk020Row($post)->status)->toBe('failed');
        Http::assertNothingSent();
    });

    it('post ainda em processing responde 409 retry_not_allowed e não consulta a rede', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        $post = stk020Post($user, 'tiktok', 90, ['container_id' => 'pub-1']);

        $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(409)->assertJsonPath('error', 'retry_not_allowed');

        expect(stk020Row($post)->status)->toBe('processing');
        Http::assertNothingSent();
        Bus::assertNotDispatched(PublishPostJob::class);
    });

    it('retry de post que a rede já publicou: 409 com código de domínio já-publicado, published_at gravado, mídia limpa', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        stk020TikTokStatus('PUBLISH_COMPLETE');
        $post = stk020Post($user, 'tiktok', 5, ['status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()]);

        $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(409)
            ->assertJsonPath('error', ScheduledPostError::ALREADY_PUBLISHED->value)
            ->assertJsonPath('message', ScheduledPostError::ALREADY_PUBLISHED->message());

        expect(stk020Row($post)->status)->toBe('published')->and(stk020Row($post)->published_at)->not->toBeNull();
        Bus::assertNotDispatched(PublishPostJob::class);
    });

    it('retry de YouTube failed com ID do vídeo já gravado: 409, vira published e não despacha job', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = stk020Post($user, 'youtube', 5, ['status' => 'failed', 'platform_post_id' => 'yt-1', 'failed_at' => now()]);

        $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertStatus(409);

        expect(stk020Row($post)->status)->toBe('published');
        Bus::assertNotDispatched(PublishPostJob::class);
    });

    it('retry quando a consulta à rede falha (5xx) não bloqueia o reenvio', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['*' => Http::response('boom', 500)]);
        $post = stk020Post($user, 'tiktok', 5, ['status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()]);

        $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertSuccessful();

        expect(stk020Row($post)->status)->toBe('pending');
    });
});

describe('RF-9 e RF-10: não duplicar o envio', function () {

    it('YouTube: com platform_post_id gravado o SDK do Google não recebe nenhuma requisição (nem upload nem thumbnail)', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = stk020Post($user, 'youtube', 1, ['platform_post_id' => 'yt-abc', 'payload' => ['thumbnail_path' => 'workspaces/w/t.jpg']]);
        $history = youtubeFakeSdk([]);

        app(YouTubeService::class)->upload($post->socialAccount, $post);

        expect($history)->toHaveCount(0)->and(stk020Row($post)->status)->toBe('published');
    });

    it('YouTube: o ID é gravado logo após o upload, mesmo que a etapa seguinte (thumbnail) falhe', function () {
        $user = createUserWithPermissions(['posts.create']);
        $thumb = 'workspaces/w/thumb.jpg';
        Storage::disk('s3')->put($thumb, 'img');
        $post = stk020Post($user, 'youtube', 1, ['payload' => ['thumbnail_path' => $thumb]]);
        youtubeFakeSdk([youtubeResumeResponse(), youtubeDoneResponse('novo-id'), youtubeErrorResponse(400, 'invalidImage')]);

        app(YouTubeService::class)->upload($post->socialAccount, $post);

        expect(stk020Row($post)->platform_post_id)->toBe('novo-id');
    });

    it('TikTok: grava o publish_id logo após o init, antes dos chunks (chunk falha e o ID fica)', function () {
        $user = createUserWithPermissions(['posts.create']);
        $account = tiktokAccount($user);
        $path = tiktokMedia($user);
        $post = tiktokPost($user, $account, $path);
        tiktokFakeApi(['put_status' => 500]);

        try {
            app(TikTokService::class)->upload($account, $post);
        } catch (Throwable) {
        }

        expect(stk020Row($post)->container_id)->toBe('v_pub_file~v2.123');
    });

    it('TikTok: publish_id já PUBLISH_COMPLETE no TikTok não inicia novo init e despacha só a conferência', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        $account = tiktokAccount($user);
        $post = tiktokPost($user, $account, tiktokMedia($user), [], ['container_id' => 'pub-antigo']);
        tiktokFakeApi(['status_data' => ['status' => 'PUBLISH_COMPLETE']]);

        app(TikTokService::class)->upload($account, $post);

        Bus::assertDispatched(CheckTikTokPostStatusJob::class);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'video/init'));
    });

    it('TikTok: publish_id anterior em FAILED permite iniciar novo envio (nada foi ao ar)', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        $account = tiktokAccount($user);
        $post = tiktokPost($user, $account, tiktokMedia($user), [], ['container_id' => 'pub-antigo']);
        tiktokFakeApi(['status_data' => ['status' => 'FAILED', 'fail_reason' => 'x']]);

        app(TikTokService::class)->upload($account, $post);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'video/init'));
        expect(stk020Row($post)->container_id)->toBe('v_pub_file~v2.123');
    });
});
