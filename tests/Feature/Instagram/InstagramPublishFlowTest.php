<?php

use App\Enums\Exceptions\InstagramError;
use App\Exceptions\InstagramException;
use App\Jobs\CheckInstagramContainerJob;
use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use App\Services\InstagramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Http::preventStrayRequests();
});

/** Post do Instagram com a mídia no S3 fake; overrides sobrescrevem os campos do post. */
function igFlowPost(array $overrides = []): array
{
    $user = createUserWithPermissions(['posts.create']);
    $account = connectedSocialAccount($user, 'instagram', ['platform_id' => 'ig-conta-1']);
    $media = $overrides['media_path'] ?? 'workspaces/w/videos/ig.mp4';
    Storage::disk('s3')->put($media, 'midia');

    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => 'instagram',
        'title' => 'Reel',
        'caption' => 'Legenda do post',
        'media_path' => $media,
        'status' => 'pending',
    ], $overrides));

    return [$account, $post];
}

describe('InstagramService::upload sem container', function () {

    it('vídeo cria container REELS com video_url, grava o container e despacha o job de verificação', function () {
        Queue::fake();
        [$account, $post] = igFlowPost();
        Http::fake(['*/ig-conta-1/media' => Http::response(['id' => 'container-novo'])]);

        app(InstagramService::class)->upload($account, $post);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ig-conta-1/media')
            && $r['media_type'] === 'REELS'
            && ! empty($r['video_url'])
            && ! isset($r['image_url'])
            && $r['caption'] === 'Legenda do post'
            && $r['access_token'] === 'token-valido');
        $fresh = $post->fresh();
        expect($fresh->status)->toBe('processing')
            ->and($fresh->container_id)->toBe('container-novo')
            ->and($fresh->container_created_at)->not->toBeNull();
        Queue::assertPushed(CheckInstagramContainerJob::class, fn ($job) => $job->containerId === 'container-novo'
            && $job->shouldPublish === true && $job->queue === 'instagram');
    });

    it('imagem usa image_url e não manda media_type', function () {
        Queue::fake();
        [$account, $post] = igFlowPost(['media_path' => 'workspaces/w/images/foto.jpg']);
        Http::fake(['*/ig-conta-1/media' => Http::response(['id' => 'container-img'])]);

        app(InstagramService::class)->upload($account, $post);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ig-conta-1/media')
            && ! empty($r['image_url'])
            && ! isset($r['video_url'])
            && ! isset($r['media_type']));
        expect($post->fresh()->container_id)->toBe('container-img');
        Queue::assertPushed(CheckInstagramContainerJob::class, 1);
    });

    it('resposta sem id lança erro, não grava container e não despacha o job', function () {
        Queue::fake();
        [$account, $post] = igFlowPost();
        Http::fake(['*/ig-conta-1/media' => Http::response(['error' => ['code' => 100, 'message' => 'Invalid parameter']], 400)]);

        expect(fn () => app(InstagramService::class)->upload($account, $post))->toThrow(Exception::class);

        expect($post->fresh()->container_id)->toBeNull();
        Queue::assertNothingPushed();
    });

});

describe('InstagramService::upload com container existente', function () {

    it('container em ERROR é zerado e recriado', function (array $statusResponse) {
        Queue::fake();
        [$account, $post] = igFlowPost(['container_id' => 'velho', 'container_created_at' => now()]);
        Http::fake([
            '*/velho*' => Http::response($statusResponse),
            '*/ig-conta-1/media' => Http::response(['id' => 'recriado']),
        ]);

        app(InstagramService::class)->upload($account, $post);

        expect($post->fresh()->container_id)->toBe('recriado');
        Queue::assertPushed(CheckInstagramContainerJob::class, fn ($job) => $job->containerId === 'recriado');
    })->with([
        'status ERROR' => [['status_code' => 'ERROR']],
        'sem status_code' => [['id' => 'velho']],
    ]);

    it('container em andamento despacha o job sem criar outro container', function () {
        Queue::fake();
        [$account, $post] = igFlowPost(['container_id' => 'andando', 'container_created_at' => now()]);
        Http::fake(['*/andando*' => Http::response(['status_code' => 'IN_PROGRESS'])]);

        app(InstagramService::class)->upload($account, $post);

        Http::assertSentCount(1);
        expect($post->fresh()->container_id)->toBe('andando');
        Queue::assertPushed(CheckInstagramContainerJob::class, fn ($job) => $job->containerId === 'andando' && $job->shouldPublish);
    });

    it('falha de conexão ao consultar o container recria o container', function () {
        Queue::fake();
        [$account, $post] = igFlowPost(['container_id' => 'velho', 'container_created_at' => now()]);
        Http::fake([
            '*/velho*' => fn () => throw new ConnectionException('timeout'),
            '*/ig-conta-1/media' => Http::response(['id' => 'recriado']),
        ]);

        app(InstagramService::class)->upload($account, $post);

        expect($post->fresh()->container_id)->toBe('recriado');
    });

    it('container com mais de 24 horas é ignorado sem consultar a Meta', function () {
        Queue::fake();
        [$account, $post] = igFlowPost(['container_id' => 'antigo', 'container_created_at' => now()->subHours(25)]);
        Http::fake(['*/ig-conta-1/media' => Http::response(['id' => 'novo'])]);

        app(InstagramService::class)->upload($account, $post);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/antigo'));
        expect($post->fresh()->container_id)->toBe('novo');
    });

    it('container pronto publica direto, grava o post e apaga a mídia do S3', function () {
        [$account, $post] = igFlowPost(['container_id' => 'pronto', 'container_created_at' => now()]);
        Http::fake([
            '*/pronto*' => Http::response(['status_code' => 'FINISHED']),
            '*/ig-conta-1/media_publish' => Http::response(['id' => 'midia-9']),
            '*/midia-9*' => Http::response(['permalink' => 'https://www.instagram.com/p/XYZ/']),
        ]);

        app(InstagramService::class)->upload($account, $post);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ig-conta-1/media_publish')
            && $r['creation_id'] === 'pronto');
        expect($post->fresh()->status)->toBe('published')
            ->and($post->fresh()->published_at)->not->toBeNull();
        Storage::disk('s3')->assertMissing($post->media_path);
    });

});

describe('InstagramService: erros tipados', function () {

    it('erro da Meta ao criar o container vira InstagramException em pt-BR, sem JSON nem texto cru', function () {
        Queue::fake();
        [$account, $post] = igFlowPost();
        Http::fake(['*/ig-conta-1/media' => Http::response(['error' => ['code' => 100, 'message' => 'Texto cru SEGREDO', 'fbtrace_id' => 'TRACE']], 400)]);

        try {
            app(InstagramService::class)->upload($account, $post);
            $this->fail('deveria lançar');
        } catch (InstagramException $e) {
            expect($e->getMessage())->toBe(InstagramError::UNKNOWN->message());
        }

        (new PublishPostJob($post))->failed($e);
        expect($post->fresh()->error_message)->toBe(InstagramError::UNKNOWN->message())
            ->and($post->fresh()->error_message)->not->toContain('SEGREDO')->not->toContain('{');
    });

    it('sem conexão ao criar o container a mensagem não traz a URL com o access_token', function () {
        Queue::fake();
        [$account, $post] = igFlowPost();
        Http::fake(['*/ig-conta-1/media' => fn () => throw new ConnectionException('timeout https://graph.instagram.com/v25.0/x?access_token=SEGREDO-IG')]);

        expect(fn () => app(InstagramService::class)->upload($account, $post))
            ->toThrow(InstagramException::class, InstagramError::API_UNAVAILABLE->message());
    });

    it('container pronto com media_publish recusado marca failed com a mensagem mapeada e mantém a mídia', function () {
        [$account, $post] = igFlowPost(['container_id' => 'pronto', 'container_created_at' => now()]);
        Http::fake([
            '*/pronto*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['error' => ['code' => 190, 'message' => 'Token SEGREDO']], 400),
        ]);

        app(InstagramService::class)->upload($account, $post);

        expect($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->toBe(InstagramError::ACCESS_DENIED->message());
        Storage::disk('s3')->assertExists($post->media_path);
    });

    it('container pronto com media_publish sem conexão propaga e não marca failed (o retry decide)', function () {
        [$account, $post] = igFlowPost(['container_id' => 'pronto', 'container_created_at' => now()]);
        Http::fake([
            '*/pronto*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => fn () => throw new ConnectionException('timeout'),
        ]);

        expect(fn () => app(InstagramService::class)->upload($account, $post))->toThrow(InstagramException::class);

        expect($post->fresh()->status)->toBe('processing');
    });

});
