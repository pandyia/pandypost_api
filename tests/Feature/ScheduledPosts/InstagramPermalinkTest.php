<?php

use App\Jobs\CheckInstagramContainerJob;
use App\Models\ScheduledPost;
use App\Services\InstagramService;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function instagramProcessingPost(): array
{
    $user = createUserWithPermissions(['posts.view']);
    $account = connectedSocialAccount($user, 'instagram');
    $post = ScheduledPost::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => 'instagram',
        'title' => 'Reel',
        'media_path' => 'workspaces/w/videos/a.mp4',
        'status' => 'processing',
        'container_id' => 'container-1',
        'container_created_at' => now(),
    ]);

    return [$user, $account, $post];
}

describe('link do post publicado no Instagram', function () {

    it('guarda o id da mídia em platform_post_id e o permalink em platform_post_url', function () {
        [$user, $account, $post] = instagramProcessingPost();
        Http::fake([
            '*/container-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => '17913722205549643']),
            '*/17913722205549643*' => Http::response(['permalink' => 'https://www.instagram.com/p/Des1N6NCInQ/', 'id' => '17913722205549643']),
        ]);

        (new CheckInstagramContainerJob($post, $account, 'container-1'))->handle();

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('published')
            ->and($fresh->platform_post_id)->toBe('17913722205549643')
            ->and($fresh->platform_post_url)->toBe('https://www.instagram.com/p/Des1N6NCInQ/')
            ->and($fresh->getPlatformPostUrl())->toBe('https://www.instagram.com/p/Des1N6NCInQ/');
    });

    it('publica mesmo se o permalink falhar, e não monta link com o id interno', function () {
        [$user, $account, $post] = instagramProcessingPost();
        Http::fake([
            '*/container-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => '17913722205549643']),
            '*/17913722205549643*' => Http::response(['error' => ['message' => 'indisponível']], 500),
        ]);

        (new CheckInstagramContainerJob($post, $account, 'container-1'))->handle();

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('published')
            ->and($fresh->platform_post_id)->toBe('17913722205549643')
            ->and($fresh->platform_post_url)->toBeNull()
            ->and($fresh->getPlatformPostUrl())->toBeNull();
    });

    it('publica mesmo se a consulta do permalink ficar sem conexão', function () {
        [$user, $account, $post] = instagramProcessingPost();
        Http::fake([
            '*/container-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => '17913722205549643']),
            '*/17913722205549643*' => fn () => throw new ConnectionException('timeout'),
        ]);

        (new CheckInstagramContainerJob($post, $account, 'container-1'))->handle();

        expect($post->fresh()->status)->toBe('published')
            ->and($post->fresh()->platform_post_url)->toBeNull();
    });

    it('guarda o permalink também quando o container já estava pronto (InstagramService)', function () {
        Storage::fake('s3');
        [$user, $account, $post] = instagramProcessingPost();
        Http::fake([
            '*/container-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => '17913722205549643']),
            '*/17913722205549643*' => Http::response(['permalink' => 'https://www.instagram.com/p/Des1N6NCInQ/']),
        ]);

        app(InstagramService::class)->upload($account, $post);

        expect($post->fresh()->status)->toBe('published')
            ->and($post->fresh()->platform_post_url)->toBe('https://www.instagram.com/p/Des1N6NCInQ/');
    });

    it('no InstagramService, publica sem link se o permalink falhar', function () {
        Storage::fake('s3');
        [$user, $account, $post] = instagramProcessingPost();
        Http::fake([
            '*/container-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => '17913722205549643']),
            '*/17913722205549643*' => fn () => throw new ConnectionException('timeout'),
        ]);

        app(InstagramService::class)->upload($account, $post);

        expect($post->fresh()->status)->toBe('published')
            ->and($post->fresh()->platform_post_id)->toBe('17913722205549643')
            ->and($post->fresh()->platform_post_url)->toBeNull();
    });

    it('expõe o permalink em platform_post_url na listagem', function () {
        [$user, $account, $post] = instagramProcessingPost();
        $post->update([
            'status' => 'published',
            'platform_post_id' => '17913722205549643',
            'platform_post_url' => 'https://www.instagram.com/p/Des1N6NCInQ/',
        ]);
        $this->mock(StorageService::class)->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/v.mp4');

        $this->withToken($user->test_token)
            ->getJson('/api/scheduled-posts')
            ->assertOk()
            ->assertJsonPath('data.0.platform_post_url', 'https://www.instagram.com/p/Des1N6NCInQ/')
            ->assertJsonPath('data.0.platform_post_id', '17913722205549643');
    });

});

function publishedInstagramPost(array $overrides = []): ScheduledPost
{
    [$user, $account, $post] = instagramProcessingPost();
    $post->update(array_merge([
        'status' => 'published',
        'platform_post_id' => '17913722205549643',
        'platform_post_url' => null,
    ], $overrides));

    return $post->fresh();
}

describe('instagram:backfill-permalinks', function () {

    it('grava o permalink dos posts publicados sem link', function () {
        $post = publishedInstagramPost();
        Http::fake([
            '*/17913722205549643*' => Http::response(['permalink' => 'https://www.instagram.com/p/Des1N6NCInQ/']),
        ]);

        $this->artisan('instagram:backfill-permalinks')->assertSuccessful();

        expect($post->fresh()->platform_post_url)->toBe('https://www.instagram.com/p/Des1N6NCInQ/')
            ->and($post->fresh()->platform_post_id)->toBe('17913722205549643');
    });

    it('no --dry-run não consulta a Graph API nem grava', function () {
        $post = publishedInstagramPost();
        Http::fake();

        $this->artisan('instagram:backfill-permalinks', ['--dry-run' => true])
            ->expectsOutputToContain('1 post(s) seriam atualizados')
            ->assertSuccessful();

        Http::assertNothingSent();
        expect($post->fresh()->platform_post_url)->toBeNull();
    });

    it('segue para os próximos posts quando um falha', function () {
        $broken = publishedInstagramPost(['platform_post_id' => '111']);
        $ok = publishedInstagramPost(['platform_post_id' => '222']);
        Http::fake([
            '*/111*' => fn () => throw new ConnectionException('timeout'),
            '*/222*' => Http::response(['permalink' => 'https://www.instagram.com/p/OK222/']),
        ]);

        $this->artisan('instagram:backfill-permalinks')
            ->expectsOutputToContain('1 post(s) atualizados, 1 com falha')
            ->assertSuccessful();

        expect($broken->fresh()->platform_post_url)->toBeNull()
            ->and($ok->fresh()->platform_post_url)->toBe('https://www.instagram.com/p/OK222/');
    });

    it('ignora posts que já têm link, sem id da mídia ou não publicados', function () {
        publishedInstagramPost(['platform_post_url' => 'https://www.instagram.com/p/JaTem/']);
        publishedInstagramPost(['platform_post_id' => null]);
        publishedInstagramPost(['status' => 'failed']);
        Http::fake();

        $this->artisan('instagram:backfill-permalinks')
            ->expectsOutputToContain('Nenhum post do Instagram sem link')
            ->assertSuccessful();

        Http::assertNothingSent();
    });

});
