<?php

use App\Jobs\CheckInstagramContainerJob;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Spec 017, ponta a ponta por rede: o membro B agenda via HTTP na conta conectada pelo A,
// o job de publicação roda com a rede mockada e o post termina publicado. Sem posts.create => 403.

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
    $this->a = createUserWithPermissions(['posts.create', 'posts.view', 'social_accounts.view']);
    $this->workspace = Workspace::withoutGlobalScopes()->find($this->a->currentAccess->workspace_id);
    $this->b = createUserWithPermissions(['posts.create', 'posts.view'], true, $this->workspace);
    $this->semPermissao = createUserWithPermissions(['posts.view'], true, $this->workspace);
});

/** Agenda via HTTP como B e devolve o post criado (e confere a fila). */
function flowSchedule(string $platform, $conta, string $media, array $extra = []): ScheduledPost
{
    test()->withToken(test()->b->test_token)->postJson('/api/scheduled-posts', array_merge([
        'media_storage_path' => $media,
        'social_account_uuids' => [$conta->uuid],
        'title' => 'Via B',
        'caption' => 'Legenda',
    ], $extra))->assertCreated();

    $post = ScheduledPost::withoutGlobalScopes()->sole();
    expect($post->user_id)->toBe(test()->b->id)->and($post->platform->value)->toBe($platform);
    Queue::assertPushed(PublishPostJob::class, 1);

    return $post;
}

/** Roda o PublishPostJob que o agendamento colocou na fila. */
function flowRunPublish(): void
{
    $job = Queue::pushed(PublishPostJob::class)->first();
    app()->call([$job, 'handle']);
}

describe('TikTok', function () {

    it('B agenda na conta conectada pelo A e o post termina published', function () {
        $conta = tiktokAccount($this->a);
        $media = tiktokMedia($this->a, 1024);
        tiktokFakeApi(['status_data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [7123456]]]);

        $post = flowSchedule('tiktok', $conta, $media, ['privacy_level' => 'SELF_ONLY']);
        flowRunPublish();

        expect($post->fresh()->status)->toBe('processing');
        $check = Queue::pushed(CheckTikTokPostStatusJob::class)->first();
        $check->withFakeQueueInteractions();
        app()->call([$check, 'handle']);

        expect($post->fresh()->status)->toBe('published')->and($conta->fresh()->user_id)->toBe($this->a->id);
    });

    it('403: sem posts.create não agenda', function () {
        $conta = tiktokAccount($this->a);
        $media = tiktokMedia($this->a);

        $this->withToken($this->semPermissao->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $media, 'social_account_uuids' => [$conta->uuid], 'privacy_level' => 'SELF_ONLY',
        ])->assertForbidden();

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

});

describe('Instagram', function () {

    it('B agenda na conta conectada pelo A e o post termina published', function () {
        Storage::fake('s3');
        Http::preventStrayRequests();
        $conta = connectedSocialAccount($this->a, 'instagram', ['platform_id' => 'ig-conta-1']);
        $media = "workspaces/{$this->workspace->uuid}/videos/ig.mp4";
        Storage::disk('s3')->put($media, 'midia');
        Http::fake([
            '*/ig-conta-1/media' => Http::response(['id' => 'cont-1']),
            '*/cont-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/ig-conta-1/media_publish' => Http::response(['id' => 'midia-1']),
            '*/midia-1*' => Http::response(['permalink' => 'https://www.instagram.com/p/AAA/']),
        ]);

        $post = flowSchedule('instagram', $conta, $media);
        flowRunPublish();

        expect($post->fresh()->status)->toBe('processing');
        $check = Queue::pushed(CheckInstagramContainerJob::class)->first();
        $check->withFakeQueueInteractions();
        $check->handle();

        expect($post->fresh()->status)->toBe('published');
    });

    it('403: sem posts.create não agenda', function () {
        Storage::fake('s3');
        $conta = connectedSocialAccount($this->a, 'instagram');

        $this->withToken($this->semPermissao->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => "workspaces/{$this->workspace->uuid}/videos/ig.mp4", 'social_account_uuids' => [$conta->uuid],
        ])->assertForbidden();

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

});

describe('YouTube (google)', function () {

    it('B agenda na conta conectada pelo A e o post termina published', function () {
        Storage::fake('s3');
        $conta = youtubeAccount($this->a);
        $media = youtubeStoredFile($this->a, 'video.mp4');
        youtubeFakeSdk([youtubeResumeResponse(), youtubeDoneResponse('yt-abc')]);

        $post = flowSchedule('youtube', $conta, $media, ['youtube_privacy_status' => 'private']);
        flowRunPublish();

        expect($post->fresh()->status)->toBe('published')->and($post->fresh()->platform_post_id)->toBe('yt-abc');
    });

    it('403: sem posts.create não agenda', function () {
        Storage::fake('s3');
        $conta = youtubeAccount($this->a);
        $media = youtubeStoredFile($this->a, 'video.mp4');

        $this->withToken($this->semPermissao->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $media, 'social_account_uuids' => [$conta->uuid], 'youtube_privacy_status' => 'private',
        ])->assertForbidden();

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

});
