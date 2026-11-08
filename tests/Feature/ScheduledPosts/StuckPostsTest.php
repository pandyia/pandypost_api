<?php

use App\Enums\Exceptions\ScheduledPostError;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\PostReconciliationService;
use App\Services\TikTokService;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
    Storage::fake('s3');
});

const TT_FETCH = 'open.tiktokapis.com/v2/post/publish/status/fetch/*';

function stuckPost(User $user, string $platform, int $minutesAgo, array $overrides = []): ScheduledPost
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

    // updated_at por query direta: o Eloquent o sobrescreveria.
    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['updated_at' => now()->subMinutes($minutesAgo)]);

    return $post->fresh();
}

function fakeTikTokStatus(string $status, array $extra = []): void
{
    Http::fake([TT_FETCH => Http::response(['data' => array_merge(['status' => $status], $extra), 'error' => ['code' => 'ok', 'message' => '', 'log_id' => 'l']])]);
}

function status(ScheduledPost $post): string
{
    return ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->value('status');
}

describe('limites por rede', function () {

    it('TikTok: 31 min sem confirmação da rede espera; só fecha como failed após 2× o limite', function () {
        $user = createUserWithPermissions(['posts.create']);
        fakeTikTokStatus('PROCESSING_DOWNLOAD');
        $p31 = stuckPost($user, 'tiktok', 31, ['container_id' => 'pub-1']);
        $p29 = stuckPost($user, 'tiktok', 29, ['container_id' => 'pub-2']);
        $p61 = stuckPost($user, 'tiktok', 61, ['container_id' => 'pub-3']);

        $this->artisan('posts:close-stuck')->assertSuccessful();

        expect(status($p31))->toBe('processing')
            ->and(status($p29))->toBe('processing')
            ->and(status($p61))->toBe('failed')
            ->and($p61->fresh()->error_message)->toBe(ScheduledPostError::STUCK_TIMEOUT->message())
            ->and($p61->fresh()->failed_at)->not->toBeNull();
        Http::assertSentCount(2); // o de 29 min nem é consultado
    });

    it('Instagram: 45 min não é consultado; 61 min com container em erro vira failed', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['*/c-*' => Http::response(['status_code' => 'ERROR'])]);
        $p45 = stuckPost($user, 'instagram', 45, ['container_id' => 'c-1']);
        $p61 = stuckPost($user, 'instagram', 61, ['container_id' => 'c-2']);

        $this->artisan('posts:close-stuck');

        expect(status($p45))->toBe('processing')->and(status($p61))->toBe('failed');
        Http::assertSentCount(1);
    });

    it('não toca em posts fora de processing', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        $ids = collect(['pending', 'published', 'cancelled'])->map(fn ($s) => stuckPost($user, 'tiktok', 500, ['status' => $s, 'container_id' => 'x']));

        $this->artisan('posts:close-stuck');

        $ids->each(fn ($p) => expect(status($p))->toBe($p->status));
        Http::assertNothingSent();
    });
});

describe('a rede decide', function () {

    it('TikTok confirma publicação: vira published, apaga a mídia e não avisa falha', function () {
        $user = createUserWithPermissions(['posts.create']);
        fakeTikTokStatus('PUBLISH_COMPLETE');
        $post = stuckPost($user, 'tiktok', 40, ['container_id' => 'pub-1']);

        $this->artisan('posts:close-stuck');

        $post->refresh();
        expect($post->status)->toBe('published')->and($post->published_at)->not->toBeNull();
        Storage::disk('s3')->assertMissing($post->media_path);
        expect(Notification::where('type', 'like', '%failed%')->count())->toBe(0);
    });

    it('TikTok informa falha: failed com texto do enum, nunca o da rede', function () {
        $user = createUserWithPermissions(['posts.create']);
        fakeTikTokStatus('FAILED', ['fail_reason' => 'spam_risk_too_many_posts']);
        $post = stuckPost($user, 'tiktok', 40, ['container_id' => 'pub-1']);

        $this->artisan('posts:close-stuck');

        $post->refresh();
        expect($post->status)->toBe('failed')->and($post->error_message)->not->toContain('spam_risk');
        Storage::disk('s3')->assertExists($post->media_path); // reenvio continua possível
    });

    it('Instagram PUBLISHED na Meta vira published', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['*/c-*' => Http::response(['status_code' => 'PUBLISHED'])]);
        $post = stuckPost($user, 'instagram', 70, ['container_id' => 'c-1']);

        $this->artisan('posts:close-stuck');

        expect(status($post))->toBe('published');
    });

    it('sem identificador não dá para conferir: failed mandando checar a conta', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        $post = stuckPost($user, 'youtube', 40);

        $this->artisan('posts:close-stuck');

        expect($post->fresh()->error_message)->toBe(ScheduledPostError::UNVERIFIABLE->message())
            ->and(status($post))->toBe('failed');
        Http::assertNothingSent();
    });

    it('YouTube com ID do vídeo gravado vira published', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = stuckPost($user, 'youtube', 40, ['platform_post_id' => 'yt-abc']);

        $this->artisan('posts:close-stuck');

        expect(status($post))->toBe('published');
    });

    it('mídia compartilhada com outra rede continua no S3 quando um post publica', function () {
        $user = createUserWithPermissions(['posts.create']);
        fakeTikTokStatus('PUBLISH_COMPLETE');
        $tiktok = stuckPost($user, 'tiktok', 40, ['container_id' => 'pub-1', 'media_path' => 'workspaces/w/videos/shared.mp4']);
        $other = stuckPost($user, 'instagram', 5, ['status' => 'pending', 'media_path' => 'workspaces/w/videos/shared.mp4']);

        $this->artisan('posts:close-stuck');

        expect(status($tiktok))->toBe('published')->and(status($other))->toBe('pending');
        Storage::disk('s3')->assertExists('workspaces/w/videos/shared.mp4');
    });
});

describe('idempotência e concorrência', function () {

    it('duas execuções geram uma única notificação de falha', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake();
        stuckPost($user, 'youtube', 40);

        $this->artisan('posts:close-stuck');
        $first = Notification::count();
        $this->artisan('posts:close-stuck');

        expect(Notification::count())->toBe($first);
    });

    it('post que virou published entre a leitura e a gravação não é sobrescrito', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = stuckPost($user, 'youtube', 40);
        // O job termina "no meio": a linha já é published quando o comando tenta fechá-la.
        ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['status' => 'published']);

        $service = app(PostReconciliationService::class);
        $closer = new ReflectionMethod($service, 'close');
        $result = $closer->invoke($service, $post, 'failed', 'x');

        expect($result)->toBeFalse()->and(status($post))->toBe('published');
    });
});

describe('promoção tardia e reenvio', function () {

    it('post fechado pelo comando é promovido se a rede confirmar depois', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = stuckPost($user, 'tiktok', 5, [
            'status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()->subHour(),
            'error_message' => ScheduledPostError::STUCK_TIMEOUT->message(),
        ]);
        fakeTikTokStatus('PUBLISH_COMPLETE');

        $this->artisan('posts:close-stuck');

        expect(status($post))->toBe('published');
    });

    it('failed por erro comum da rede não é promovido', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = stuckPost($user, 'tiktok', 5, [
            'status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()->subHour(), 'error_message' => 'Outro erro',
        ]);
        Http::fake();

        $this->artisan('posts:close-stuck');

        expect(status($post))->toBe('failed');
        Http::assertNothingSent();
    });

    it('/retry de post que a rede já publicou responde 409 e vira published, sem despachar job', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = stuckPost($user, 'tiktok', 5, ['status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()]);
        fakeTikTokStatus('PUBLISH_COMPLETE');

        $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertStatus(409);

        expect(status($post))->toBe('published');
        Bus::assertNotDispatched(PublishPostJob::class);
    });

    it('/retry de post que a rede não tem segue normal para pending', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = stuckPost($user, 'tiktok', 5, ['status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()]);
        fakeTikTokStatus('FAILED', ['fail_reason' => 'x']);

        $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertSuccessful();

        expect(status($post))->toBe('pending');
    });
});

describe('antiduplicidade no envio', function () {

    it('TikTok: upload repetido com publish_id já recebido pela rede não inicia outro Direct Post', function () {
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = stuckPost($user, 'tiktok', 1, ['container_id' => 'pub-1', 'payload' => ['privacy_level' => 'SELF_ONLY']]);
        $account = $post->socialAccount;
        $account->update(['scopes' => TIKTOK_SCOPES_OK]);
        Http::fake([
            TT_FETCH => Http::response(['data' => ['status' => 'PROCESSING_DOWNLOAD'], 'error' => ['code' => 'ok']]),
            '*creator_info*' => Http::response(['data' => ['privacy_level_options' => ['SELF_ONLY']], 'error' => ['code' => 'ok']]),
        ]);

        app(TikTokService::class)->upload($account->fresh(), $post);

        Bus::assertDispatched(CheckTikTokPostStatusJob::class);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'inbox/video/init') || str_contains($r->url(), 'video/init'));
    });

    it('YouTube: com videoId já gravado não reenvia o vídeo', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = stuckPost($user, 'youtube', 1, ['platform_post_id' => 'yt-abc']);
        Http::fake();

        app(YouTubeService::class)->upload($post->socialAccount, $post);

        expect(status($post))->toBe('published');
        Http::assertNothingSent();
    });
});
