<?php

use App\Jobs\CheckTikTokPostStatusJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\InstagramService;
use App\Services\Storage\StorageService;
use App\Services\TikTok\TikTokApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Caracterização (spec 014): quais arquivos do S3 cada caminho apaga (vídeo + thumbnail do payload).

const MEDIA_VIDEO = 'workspaces/w/videos/m.mp4';
const MEDIA_THUMB = 'workspaces/w/thumbnails/m.jpg';

beforeEach(function () {
    Storage::fake('s3');
    Storage::disk('s3')->put(MEDIA_VIDEO, 'video');
    Storage::disk('s3')->put(MEDIA_THUMB, 'img');
});

function mediaPost(string $platform, string $status, bool $withThumb, array $overrides = []): ScheduledPost
{
    $user = createUserWithPermissions(['posts.create', 'posts.delete']);
    $account = connectedSocialAccount($user, $platform);

    return ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => $platform,
        'title' => 'Vídeo',
        'media_path' => MEDIA_VIDEO,
        'payload' => $withThumb ? ['thumbnail_path' => MEDIA_THUMB] : [],
        'status' => $status,
    ], $overrides));
}

describe('sucesso apaga vídeo e thumbnail', function () {

    it('TikTok: PUBLISH_COMPLETE com thumbnail apaga os dois', function () {
        $post = mediaPost('tiktok', 'processing', true);
        $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);
        Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['status' => 'PUBLISH_COMPLETE'], 'error' => ['code' => 'ok']])]);

        (new CheckTikTokPostStatusJob($post, $account, 'pub-1'))->handle(app(StorageService::class), app(TikTokApi::class));

        expect($post->fresh()->status)->toBe('published');
        Storage::disk('s3')->assertMissing(MEDIA_VIDEO);
        Storage::disk('s3')->assertMissing(MEDIA_THUMB);
    });

    it('TikTok: sem thumbnail apaga só o vídeo', function () {
        $post = mediaPost('tiktok', 'processing', false);
        $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);
        Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['status' => 'PUBLISH_COMPLETE'], 'error' => ['code' => 'ok']])]);

        (new CheckTikTokPostStatusJob($post, $account, 'pub-1'))->handle(app(StorageService::class), app(TikTokApi::class));

        Storage::disk('s3')->assertMissing(MEDIA_VIDEO);
        Storage::disk('s3')->assertExists(MEDIA_THUMB);
    });

    it('Instagram: container pronto publica e apaga vídeo e thumbnail', function () {
        $post = mediaPost('instagram', 'pending', true, ['container_id' => 'c-1', 'container_created_at' => now()]);
        $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);
        Http::fake([
            '*/c-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => 'media-1']),
            '*/media-1*' => Http::response(['permalink' => 'https://www.instagram.com/reel/ABC/']),
        ]);

        app(InstagramService::class)->upload($account, $post);

        expect($post->fresh()->status)->toBe('published');
        Storage::disk('s3')->assertMissing(MEDIA_VIDEO);
        Storage::disk('s3')->assertMissing(MEDIA_THUMB);
    });

    it('Instagram: sem thumbnail apaga só o vídeo', function () {
        $post = mediaPost('instagram', 'pending', false, ['container_id' => 'c-2', 'container_created_at' => now()]);
        $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);
        Http::fake([
            '*/c-2*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => 'media-2']),
            '*/media-2*' => Http::response(['permalink' => 'https://www.instagram.com/reel/DEF/']),
        ]);

        app(InstagramService::class)->upload($account, $post);

        Storage::disk('s3')->assertMissing(MEDIA_VIDEO);
        Storage::disk('s3')->assertExists(MEDIA_THUMB);
    });

});

describe('cancelar e limpar falhos', function () {

    it('cancelar post pendente apaga vídeo e thumbnail', function () {
        $post = mediaPost('youtube', 'pending', true);

        $this->withToken($post->user->createToken('t')->plainTextToken)
            ->deleteJson("/api/scheduled-posts/{$post->uuid}")
            ->assertSuccessful();

        expect($post->fresh()->status)->toBe('cancelled');
        Storage::disk('s3')->assertMissing(MEDIA_VIDEO);
        Storage::disk('s3')->assertMissing(MEDIA_THUMB);
    });

    it('cancelar post pendente sem thumbnail apaga só o vídeo', function () {
        $post = mediaPost('youtube', 'pending', false);

        $this->withToken($post->user->createToken('t')->plainTextToken)
            ->deleteJson("/api/scheduled-posts/{$post->uuid}")
            ->assertSuccessful();

        Storage::disk('s3')->assertMissing(MEDIA_VIDEO);
        Storage::disk('s3')->assertExists(MEDIA_THUMB);
    });

    it('posts:purge-failed-media apaga vídeo e thumbnail do post falho vencido', function () {
        $post = mediaPost('youtube', 'failed', true);
        ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['failed_at' => now()->subDays(6)]);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertMissing(MEDIA_VIDEO);
        Storage::disk('s3')->assertMissing(MEDIA_THUMB);
        expect($post->fresh()->media_purged_at)->not->toBeNull();
    });

    it('reenvio com vídeo e thumbnail no storage é aceito; sem a thumbnail é media_expired', function () {
        Queue::fake();
        $post = mediaPost('youtube', 'failed', true);
        ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['failed_at' => now()->subDay()]);
        $token = $post->user->createToken('t')->plainTextToken;

        Storage::disk('s3')->delete(MEDIA_THUMB);
        $this->withToken($token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(422)->assertJsonPath('error', 'media_expired');

        Storage::disk('s3')->put(MEDIA_THUMB, 'img');
        $this->withToken($token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertOk();
    });

});
