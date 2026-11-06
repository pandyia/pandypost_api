<?php

use App\Jobs\CheckInstagramContainerJob;
use App\Models\ScheduledPost;
use App\Services\InstagramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Http::preventStrayRequests();
});

function igWarmupPost(array $overrides = [], string $platform = 'instagram'): array
{
    $user = createUserWithPermissions(['posts.create']);
    $account = connectedSocialAccount($user, $platform, ['platform_id' => 'ig-conta-1']);
    Storage::disk('s3')->put('workspaces/w/videos/w.mp4', 'midia');

    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => $platform,
        'title' => 'Reel',
        'media_path' => 'workspaces/w/videos/w.mp4',
        'status' => 'pending',
        'scheduled_at' => now()->addMinutes(30),
    ], $overrides));

    return [$account, $post];
}

describe('InstagramService::prepare', function () {

    it('cria o container sem publicar e despacha o job com shouldPublish=false', function () {
        Queue::fake();
        [$account, $post] = igWarmupPost();
        Http::fake(['*/ig-conta-1/media' => Http::response(['id' => 'c-warm'])]);

        app(InstagramService::class)->prepare($account, $post);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'media_publish'));
        $fresh = $post->fresh();
        expect($fresh->status)->toBe('pending')->and($fresh->container_id)->toBe('c-warm');
        Queue::assertPushed(CheckInstagramContainerJob::class, fn ($job) => $job->shouldPublish === false && $job->containerId === 'c-warm');
    });

    it('engole o erro (melhor-esforço), enquanto upload propaga', function () {
        Queue::fake();
        [$account, $post] = igWarmupPost();
        Http::fake(['*/ig-conta-1/media' => Http::response(['error' => ['code' => 100, 'message' => 'x']], 400)]);

        app(InstagramService::class)->prepare($account, $post);
        expect($post->fresh()->container_id)->toBeNull();

        expect(fn () => app(InstagramService::class)->upload($account, $post))->toThrow(Exception::class);
        Queue::assertNothingPushed();
    });

});

describe('posts:warmup', function () {

    it('prepara só Instagram pendente na janela de 60 minutos e pula quem já tem container válido', function () {
        Queue::fake();
        [, $inWindow] = igWarmupPost();
        [, $outOfWindow] = igWarmupPost(['scheduled_at' => now()->addMinutes(90)]);
        [, $otherPlatform] = igWarmupPost([], 'youtube');
        [, $withContainer] = igWarmupPost(['container_id' => 'ja-tem', 'container_created_at' => now()]);
        Http::fake(['*/media' => Http::response(['id' => 'c-warm'])]);

        $this->artisan('posts:warmup')->assertSuccessful();

        expect($inWindow->fresh()->container_id)->toBe('c-warm')
            ->and($outOfWindow->fresh()->container_id)->toBeNull()
            ->and($otherPlatform->fresh()->container_id)->toBeNull()
            ->and($withContainer->fresh()->container_id)->toBe('ja-tem');
        Queue::assertPushed(CheckInstagramContainerJob::class, 1);
    });

});
