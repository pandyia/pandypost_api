<?php

use App\Enums\Exceptions\ScheduledPostError;
use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Storage::fake('s3');
});

function secStuck(User $user, string $platform, array $overrides = [], ?array $account = null): ScheduledPost
{
    $media = 'workspaces/w/videos/'.uniqid().'.mp4';
    Storage::disk('s3')->put($media, 'video');

    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => connectedSocialAccount($user, $platform, $account ?? [])->id,
        'platform' => $platform,
        'title' => 'Preso',
        'status' => 'processing',
        'payload' => [],
        'media_path' => $media,
    ], $overrides));

    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['updated_at' => now()->subMinutes(70)]);

    return $post->fresh();
}

function secTtStatus(string $status, array $extra = []): void
{
    Http::fake(['open.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response(['data' => array_merge(['status' => $status], $extra), 'error' => ['code' => 'ok']])]);
}

describe('spec 020: segurança da rede de segurança de posts presos', function () {

    it('regressão: /retry de post de outro workspace dá 404, não consulta a rede e nada muda', function () {
        $this->withoutMiddleware(ThrottleRequests::class);
        Bus::fake();
        Http::fake();
        $owner = createUserWithPermissions(['posts.create']);
        $attacker = createUserWithPermissions(['posts.create']);
        $post = secStuck($owner, 'tiktok', ['status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()]);

        $this->actingAs($attacker)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertNotFound();

        Http::assertNothingSent();
        Bus::assertNotDispatched(PublishPostJob::class);
        expect(ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->value('status'))->toBe('failed');
    });

    it('regressão: fail_reason hostil da rede nunca vai para error_message', function () {
        $user = createUserWithPermissions(['posts.create']);
        secTtStatus('FAILED', ['fail_reason' => 'Bearer SEGREDO-123 https://evil.test/?t=x']);
        $post = secStuck($user, 'tiktok', ['container_id' => 'pub-1']);

        $this->artisan('posts:close-stuck');

        $post->refresh();
        expect($post->status)->toBe('failed')
            ->and($post->error_message)->not->toContain('SEGREDO')->not->toContain('evil.test');
    });

    it('regressão: exceção de terceiros com token na mensagem não vai para o log', function () {
        $user = createUserWithPermissions(['posts.create']);
        Http::fake(['open.tiktokapis.com/*' => fn () => throw new RuntimeException('cURL error token=SEGREDO-123')]);
        secStuck($user, 'tiktok', ['container_id' => 'pub-1']);
        Log::spy();

        $this->artisan('posts:close-stuck');

        Log::shouldHaveReceived('warning')->atLeast()->once();
        Log::shouldNotHaveReceived('warning', fn ($msg) => str_contains((string) $msg, 'SEGREDO'));
    });

    it('regressão: o comando consulta a rede com o token da conta do próprio post (sem misturar workspaces)', function () {
        $a = createUserWithPermissions(['posts.create']);
        $b = createUserWithPermissions(['posts.create']);
        secTtStatus('PUBLISH_COMPLETE');
        secStuck($a, 'tiktok', ['container_id' => 'pub-A'], ['access_token' => 'TOKEN-A']);
        secStuck($b, 'tiktok', ['container_id' => 'pub-B'], ['access_token' => 'TOKEN-B']);

        $this->artisan('posts:close-stuck');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['publish_id'] === 'pub-A' && $r->hasHeader('Authorization', 'Bearer TOKEN-A'));
        Http::assertSent(fn (Request $r) => $r['publish_id'] === 'pub-B' && $r->hasHeader('Authorization', 'Bearer TOKEN-B'));
    });

    it('regressão: spam de /retry gera no máximo uma consulta à rede por post', function () {
        $this->withoutMiddleware(ThrottleRequests::class);
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        secTtStatus('FAILED', ['fail_reason' => 'x']);
        $post = secStuck($user, 'tiktok', ['status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now()]);

        foreach (range(1, 10) as $_) {
            $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry");
        }

        Http::assertSentCount(1);
    });

    it('/retry não reenvia enquanto o TikTok ainda está processando o vídeo anterior (duplicidade)', function () {
        $this->withoutMiddleware(ThrottleRequests::class);
        Bus::fake();
        $user = createUserWithPermissions(['posts.create']);
        secTtStatus('PROCESSING_PUBLISH'); // a rede ainda vai publicar o envio anterior
        $post = secStuck($user, 'tiktok', [
            'status' => 'failed', 'container_id' => 'pub-1', 'failed_at' => now(),
            'error_message' => ScheduledPostError::STUCK_TIMEOUT->message(),
        ]);

        $this->actingAs($user)->postJson("/api/scheduled-posts/{$post->uuid}/retry");

        Bus::assertNotDispatched(PublishPostJob::class);
        expect(ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->value('status'))->toBe('failed');
    });
});
