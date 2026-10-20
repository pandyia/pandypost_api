<?php

use App\Models\ScheduledPost;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->user = createUserWithPermissions(['posts.view']);
});

function postWithFile(object $test, string $status, ?int $failedDaysAgo, array $overrides = []): ScheduledPost
{
    $path = $overrides['media_path'] ?? 'workspaces/w/videos/'.uniqid().'.mp4';
    Storage::disk('s3')->put($path, 'video');

    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $test->user->id,
        'social_account_id' => connectedSocialAccount($test->user, 'instagram')->id,
        'platform' => 'instagram',
        'title' => 'Vídeo',
        'media_path' => $path,
        'status' => $status,
    ], $overrides));

    // Query direta não dispara os observers.
    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update([
        'failed_at' => $failedDaysAgo === null ? null : now()->subDays($failedDaysAgo),
    ]);

    return $post->fresh();
}

describe('posts:purge-failed-media', function () {

    it('apaga a mídia de post com falha há mais de 5 dias e mantém o registro com can_retry falso', function () {
        $post = postWithFile($this, 'failed', 6, ['payload' => ['thumbnail_path' => 'workspaces/w/thumbnails/t.jpg']]);
        Storage::disk('s3')->put('workspaces/w/thumbnails/t.jpg', 'thumb');

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertMissing($post->media_path);
        Storage::disk('s3')->assertMissing('workspaces/w/thumbnails/t.jpg');
        $fresh = $post->fresh();
        expect($fresh->media_purged_at)->not->toBeNull()
            ->and($fresh->status)->toBe('failed')
            ->and($fresh->canRetry())->toBeFalse();
    });

    it('mantém a mídia de post com falha há menos de 5 dias', function () {
        $post = postWithFile($this, 'failed', 4);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertExists($post->media_path);
        expect($post->fresh()->media_purged_at)->toBeNull()
            ->and($post->fresh()->canRetry())->toBeTrue();
    });

    it('não mexe em posts que não estão failed, mesmo antigos', function (string $status) {
        $post = postWithFile($this, $status, 30);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertExists($post->media_path);
        expect($post->fresh()->media_purged_at)->toBeNull();
    })->with(['pending', 'processing', 'published']);

    it('não apaga o arquivo se outro post pendente ainda o usa, mas encerra a retenção do post falho', function () {
        $failed = postWithFile($this, 'failed', 6, ['media_path' => 'workspaces/w/videos/shared.mp4']);
        postWithFile($this, 'pending', null, ['media_path' => 'workspaces/w/videos/shared.mp4']);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertExists('workspaces/w/videos/shared.mp4');
        expect($failed->fresh()->media_purged_at)->not->toBeNull();
    });

    it('não apaga o arquivo enquanto outro post falho do mesmo arquivo ainda está no prazo', function () {
        $old = postWithFile($this, 'failed', 6, ['media_path' => 'workspaces/w/videos/shared.mp4']);
        $recent = postWithFile($this, 'failed', 1, ['media_path' => 'workspaces/w/videos/shared.mp4']);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertExists('workspaces/w/videos/shared.mp4');
        expect($old->fresh()->media_purged_at)->not->toBeNull()
            ->and($recent->fresh()->canRetry())->toBeTrue();
    });

    it('é idempotente: rodar de novo não reprocessa posts já limpos', function () {
        $post = postWithFile($this, 'failed', 6);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();
        $purgedAt = $post->fresh()->media_purged_at;
        $this->travel(1)->hour();
        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        expect($post->fresh()->media_purged_at->equalTo($purgedAt))->toBeTrue();
    });

});

describe('deleteIfUnused com posts falhos', function () {

    it('não apaga o arquivo quando um post falho dentro do prazo ainda o usa (ex.: YouTube publica, Instagram falhou)', function () {
        $instagram = postWithFile($this, 'failed', 1, ['media_path' => 'workspaces/w/videos/shared.mp4']);
        $youtube = postWithFile($this, 'processing', null, ['media_path' => 'workspaces/w/videos/shared.mp4', 'platform' => 'youtube']);

        app(StorageService::class)->deleteIfUnused([$youtube->media_path], $youtube->id);

        Storage::disk('s3')->assertExists('workspaces/w/videos/shared.mp4');
        expect($instagram->fresh()->canRetry())->toBeTrue();
    });

    it('apaga o arquivo quando o post falho já teve a retenção encerrada', function () {
        $failed = postWithFile($this, 'failed', 6, ['media_path' => 'workspaces/w/videos/shared.mp4']);
        ScheduledPost::withoutGlobalScopes()->whereKey($failed->id)->update(['media_purged_at' => now()]);
        $youtube = postWithFile($this, 'processing', null, ['media_path' => 'workspaces/w/videos/shared.mp4', 'platform' => 'youtube']);

        app(StorageService::class)->deleteIfUnused([$youtube->media_path], $youtube->id);

        Storage::disk('s3')->assertMissing('workspaces/w/videos/shared.mp4');
    });

});
