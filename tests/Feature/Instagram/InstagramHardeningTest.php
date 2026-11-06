<?php

use App\Enums\Exceptions\InstagramError;
use App\Jobs\CheckInstagramContainerJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\InstagramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Spec 018 RF-3 e RF-6: container do job, warmup que não derruba o post, PUBLISHED/EXPIRED e timeout do media_publish.

beforeEach(function () {
    Storage::fake('s3');
    Http::preventStrayRequests();
});

function hardenPost(array $overrides = []): array
{
    $user = createUserWithPermissions(['posts.create']);
    $account = connectedSocialAccount($user, 'instagram', ['platform_id' => 'ig-conta-1']);
    Storage::disk('s3')->put('workspaces/w/videos/h.mp4', 'midia');

    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => 'instagram',
        'title' => 'Reel',
        'media_path' => 'workspaces/w/videos/h.mp4',
        'status' => 'processing',
        'container_id' => 'cj-novo',
        'container_created_at' => now(),
    ], $overrides));

    return [$account, $post];
}

function hardenRun(ScheduledPost $post, SocialAccount $account, string $containerId, bool $shouldPublish = true): CheckInstagramContainerJob
{
    $job = new CheckInstagramContainerJob($post, $account, $containerId, $shouldPublish);
    $job->withFakeQueueInteractions();
    $job->job->attempts = 1;
    $job->handle();

    return $job;
}

describe('publica o container do job', function () {

    it('job do container antigo nunca publica o container que o post ganhou depois', function () {
        [$account, $post] = hardenPost(); // o post já aponta para cj-novo
        Http::fake([
            '*/cj-antigo*' => Http::response(['status_code' => 'FINISHED']),
            '*/ig-conta-1/media_publish' => Http::response(['id' => 'midia-1']),
            '*/midia-1*' => Http::response(['permalink' => 'https://www.instagram.com/p/AAA/']),
        ]);

        hardenRun($post, $account, 'cj-antigo');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'cj-antigo');
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'cj-novo');
    });

    it('upload com container válido e pronto publica o container do post', function () {
        Queue::fake();
        [$account, $post] = hardenPost(['status' => 'pending']);
        Http::fake([
            '*/cj-novo*' => Http::response(['status_code' => 'FINISHED']),
            '*/ig-conta-1/media_publish' => Http::response(['id' => 'midia-2']),
            '*/midia-2*' => Http::response(['permalink' => 'https://www.instagram.com/p/BBB/']),
        ]);

        app(InstagramService::class)->upload($account, $post);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'cj-novo');
        expect($post->fresh()->status)->toBe('published');
    });

});

describe('job de verificação só age em post pending/processing', function () {

    it('post cancelado, publicado ou falho: não consulta nem publica', function (string $status) {
        [$account, $post] = hardenPost(['status' => $status]);

        hardenRun($post, $account, 'cj-novo');

        Http::assertNothingSent();
        expect($post->fresh()->status)->toBe($status);
    })->with(['cancelled', 'published', 'failed']);

});

describe('warmup não derruba o post', function () {

    it('container ERROR no warmup só limpa o container e o post segue pendente', function () {
        [$account, $post] = hardenPost(['status' => 'pending']);
        Http::fake(['*/cj-novo*' => Http::response(['status_code' => 'ERROR'])]);

        hardenRun($post, $account, 'cj-novo', false);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('pending')
            ->and($fresh->container_id)->toBeNull()
            ->and($fresh->error_message)->toBeNull();
    });

    it('token inválido no warmup não falha o post', function () {
        [$account, $post] = hardenPost(['status' => 'pending']);
        Http::fake(['*/cj-novo*' => Http::response(['error' => ['code' => 190]], 401)]);

        hardenRun($post, $account, 'cj-novo', false);

        expect($post->fresh()->status)->toBe('pending');
    });

    it('container ERROR na publicação continua falhando o post', function () {
        [$account, $post] = hardenPost();
        Http::fake(['*/cj-novo*' => Http::response(['status_code' => 'ERROR'])]);

        hardenRun($post, $account, 'cj-novo');

        expect($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->toBe(InstagramError::CONTAINER_FAILED->message());
    });

});

describe('media_publish que deu timeout depois de a Meta publicar', function () {

    it('o retry vê o container PUBLISHED e fecha o post como publicado, sem republicar', function () {
        [$account, $post] = hardenPost();
        Http::fake(['*/cj-novo*' => Http::response(['status_code' => 'PUBLISHED'])]);

        $job = hardenRun($post, $account, 'cj-novo');

        $job->assertNotReleased();
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/media_publish'));
        $fresh = $post->fresh();
        expect($fresh->status)->toBe('published')
            ->and($fresh->published_at)->not->toBeNull()
            ->and($fresh->platform_post_id)->toBeNull()
            ->and($fresh->platform_post_url)->toBeNull();
        Storage::disk('s3')->assertMissing($post->media_path);
    });

    it('fluxo completo: timeout no media_publish propaga, o retry encontra PUBLISHED e o post não termina failed', function () {
        [$account, $post] = hardenPost();
        Http::fake([
            '*/cj-novo*' => Http::sequence()
                ->push(['status_code' => 'FINISHED'])
                ->push(['status_code' => 'PUBLISHED']),
            '*/media_publish' => fn () => throw new ConnectionException('timeout'),
        ]);

        expect(fn () => hardenRun($post, $account, 'cj-novo'))->toThrow(Exception::class);
        expect($post->fresh()->status)->toBe('processing');

        hardenRun($post, $account, 'cj-novo');

        expect($post->fresh()->status)->toBe('published');
    });

    it('erro do media_publish com o container já PUBLISHED não marca failed', function () {
        [$account, $post] = hardenPost();
        Http::fake([
            '*/cj-novo*' => Http::sequence()
                ->push(['status_code' => 'FINISHED'])
                ->push(['status_code' => 'PUBLISHED']),
            '*/media_publish' => Http::response(['error' => ['code' => 100, 'message' => 'already']], 400),
        ]);

        hardenRun($post, $account, 'cj-novo');

        expect($post->fresh()->status)->toBe('published');
    });

    it('erro do media_publish com o container ainda FINISHED continua falhando o post', function () {
        [$account, $post] = hardenPost();
        Http::fake([
            '*/cj-novo*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['error' => ['code' => 100]], 400),
        ]);

        hardenRun($post, $account, 'cj-novo');

        expect($post->fresh()->status)->toBe('failed');
    });

    it('PublishPostJob retentando com container PUBLISHED no post fecha como publicado sem republicar', function () {
        Queue::fake();
        [$account, $post] = hardenPost();
        Http::fake(['*/cj-novo*' => Http::response(['status_code' => 'PUBLISHED'])]);

        app(InstagramService::class)->upload($account, $post);

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/media_publish'));
        expect($post->fresh()->status)->toBe('published');
        Queue::assertNotPushed(CheckInstagramContainerJob::class);
    });

    it('container EXPIRED é refeito em vez de ficar em loop de espera', function () {
        Queue::fake();
        [$account, $post] = hardenPost();
        Http::fake([
            '*/cj-novo*' => Http::response(['status_code' => 'EXPIRED']),
            '*/ig-conta-1/media' => Http::response(['id' => 'cj-refeito']),
        ]);

        app(InstagramService::class)->upload($account, $post);

        expect($post->fresh()->container_id)->toBe('cj-refeito');
        Queue::assertPushed(CheckInstagramContainerJob::class, fn ($j) => $j->containerId === 'cj-refeito');
    });

    it('job de verificação com container EXPIRED falha o post na publicação', function () {
        [$account, $post] = hardenPost();
        Http::fake(['*/cj-novo*' => Http::response(['status_code' => 'EXPIRED'])]);

        hardenRun($post, $account, 'cj-novo');

        expect($post->fresh()->status)->toBe('failed');
    });

});

describe('container sem legenda', function () {

    it('não manda o campo caption quando o post não tem legenda', function () {
        Queue::fake();
        [$account, $post] = hardenPost(['status' => 'pending', 'container_id' => null, 'container_created_at' => null, 'caption' => null]);
        Http::fake(['*/ig-conta-1/media' => Http::response(['id' => 'cj-sem-legenda'])]);

        app(InstagramService::class)->upload($account, $post);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ig-conta-1/media') && ! array_key_exists('caption', $r->data()) && $r['media_type'] === 'REELS');
    });

});
