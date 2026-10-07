<?php

use App\Exceptions\ScheduledPostException;
use App\Jobs\CheckInstagramContainerJob;
use App\Services\InstagramService;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const INSTAGRAM_GRAPH = 'https://graph.instagram.com/v25.0';
const INSTAGRAM_USER_ID = '17841400000';

beforeEach(function () {
    Bus::fake();
    Storage::fake('s3');
    $this->partialMock(StorageService::class)
        ->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/midia-assinada');

    $this->account = createSocialAccount('instagram', createUserWithPermissions(), ['platform_id' => INSTAGRAM_USER_ID]);
    $this->post = createScheduledPost($this->account, [
        'caption' => 'Legenda do Reels',
        'payload' => ['thumbnail_path' => 'workspaces/x/thumbnails/capa.jpg'],
    ]);

    Storage::disk('s3')->put($this->post->media_path, 'video');
    Storage::disk('s3')->put('workspaces/x/thumbnails/capa.jpg', 'capa');
});

function withValidContainer($post): void
{
    $post->update(['container_id' => 'container_existente', 'container_created_at' => now()->subHour()]);
}

describe('criação do container', function () {

    it('cria um container de Reels para vídeos e passa a acompanhar', function (string $extension) {
        Http::fake([INSTAGRAM_GRAPH.'/'.INSTAGRAM_USER_ID.'/media' => Http::response(['id' => 'container_novo'])]);
        $this->post->update(['media_path' => str_replace('.mp4', ".{$extension}", $this->post->media_path)]);

        app(InstagramService::class)->upload($this->account, $this->post);

        Http::assertSent(fn (Request $request) => $request->url() === INSTAGRAM_GRAPH.'/'.INSTAGRAM_USER_ID.'/media'
            && $request['media_type'] === 'REELS'
            && $request['video_url'] === 'https://s3.test/midia-assinada'
            && $request['caption'] === 'Legenda do Reels'
            && $request['access_token'] === 'instagram_access_token');

        $post = $this->post->fresh();
        expect($post->status)->toBe('processing')
            ->and($post->container_id)->toBe('container_novo')
            ->and($post->container_created_at)->not->toBeNull();

        Bus::assertDispatched(CheckInstagramContainerJob::class, fn ($job) => $job->containerId === 'container_novo' && $job->shouldPublish);
    })->with(['mp4', 'mov']);

    it('cria um container de imagem para fotos', function () {
        Http::fake([INSTAGRAM_GRAPH.'/*' => Http::response(['id' => 'container_novo'])]);
        $this->post->update(['media_path' => str_replace('videos/video.mp4', 'images/foto.jpg', $this->post->media_path)]);

        app(InstagramService::class)->upload($this->account, $this->post);

        Http::assertSent(fn (Request $request) => $request['image_url'] === 'https://s3.test/midia-assinada'
            && ! isset($request['media_type']));
    });

    it('lança erro de domínio com o motivo do Instagram quando o container é recusado', function () {
        Http::fake([INSTAGRAM_GRAPH.'/*' => Http::response(['error' => ['message' => 'Invalid media']], 400)]);

        expect(fn () => app(InstagramService::class)->upload($this->account, $this->post))
            ->toThrow(ScheduledPostException::class, 'Falha ao publicar no Instagram: Invalid media');

        Bus::assertNotDispatched(CheckInstagramContainerJob::class);
    });
});

describe('container já criado (warmup)', function () {

    it('publica na hora quando o container já está pronto e limpa os arquivos', function () {
        withValidContainer($this->post);
        Http::fake([
            INSTAGRAM_GRAPH.'/container_existente*' => Http::response(['status_code' => 'FINISHED']),
            INSTAGRAM_GRAPH.'/'.INSTAGRAM_USER_ID.'/media_publish' => Http::response(['id' => 'midia_publicada']),
            INSTAGRAM_GRAPH.'/midia_publicada*' => Http::response(['permalink' => 'https://www.instagram.com/reel/AbC123/']),
        ]);

        app(InstagramService::class)->upload($this->account, $this->post);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/media_publish')
            && $request['creation_id'] === 'container_existente');

        $post = $this->post->fresh();
        expect($post->status)->toBe('published')
            ->and($post->platform_post_id)->toBe('midia_publicada')
            ->and($post->published_at)->not->toBeNull()
            ->and($post->payload['permalink'])->toBe('https://www.instagram.com/reel/AbC123/')
            ->and($post->getPlatformPostUrl())->toBe('https://www.instagram.com/reel/AbC123/');

        Storage::disk('s3')->assertMissing($this->post->media_path);
        Storage::disk('s3')->assertMissing('workspaces/x/thumbnails/capa.jpg');
        Bus::assertNotDispatched(CheckInstagramContainerJob::class);
    });

    it('lança erro de domínio quando a publicação é recusada, para o PublishPostJob tentar de novo', function () {
        withValidContainer($this->post);
        Http::fake([
            INSTAGRAM_GRAPH.'/container_existente*' => Http::response(['status_code' => 'FINISHED']),
            INSTAGRAM_GRAPH.'/'.INSTAGRAM_USER_ID.'/media_publish' => Http::response(['error' => ['message' => 'Limite atingido']], 400),
        ]);

        expect(fn () => app(InstagramService::class)->upload($this->account, $this->post))
            ->toThrow(ScheduledPostException::class, 'Falha ao publicar no Instagram: Limite atingido');

        Storage::disk('s3')->assertExists($this->post->media_path);
    });

    it('publica sem link quando o Instagram não devolve o permalink', function () {
        withValidContainer($this->post);
        Http::fake([
            INSTAGRAM_GRAPH.'/container_existente*' => Http::response(['status_code' => 'FINISHED']),
            INSTAGRAM_GRAPH.'/'.INSTAGRAM_USER_ID.'/media_publish' => Http::response(['id' => 'midia_publicada']),
            INSTAGRAM_GRAPH.'/midia_publicada*' => Http::response(['error' => ['message' => 'falhou']], 500),
        ]);

        app(InstagramService::class)->upload($this->account, $this->post);

        $post = $this->post->fresh();
        expect($post->status)->toBe('published')
            ->and($post->getPlatformPostUrl())->toBeNull();
    });

    it('cria outro container quando o existente deu erro', function () {
        withValidContainer($this->post);
        Http::fake([
            INSTAGRAM_GRAPH.'/container_existente*' => Http::response(['status_code' => 'ERROR']),
            INSTAGRAM_GRAPH.'/'.INSTAGRAM_USER_ID.'/media' => Http::response(['id' => 'container_novo']),
        ]);

        app(InstagramService::class)->upload($this->account, $this->post);

        expect($this->post->fresh()->container_id)->toBe('container_novo');
        Bus::assertDispatched(CheckInstagramContainerJob::class, fn ($job) => $job->containerId === 'container_novo');
    });

    it('só acompanha quando o container ainda está processando', function () {
        withValidContainer($this->post);
        Http::fake([INSTAGRAM_GRAPH.'/container_existente*' => Http::response(['status_code' => 'IN_PROGRESS'])]);

        app(InstagramService::class)->upload($this->account, $this->post);

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
        Bus::assertDispatched(CheckInstagramContainerJob::class, fn ($job) => $job->containerId === 'container_existente' && $job->shouldPublish);
    });

    it('ignora container com mais de 24 horas e cria outro', function () {
        $this->post->update(['container_id' => 'container_velho', 'container_created_at' => now()->subHours(25)]);
        Http::fake([INSTAGRAM_GRAPH.'/'.INSTAGRAM_USER_ID.'/media' => Http::response(['id' => 'container_novo'])]);

        app(InstagramService::class)->upload($this->account, $this->post);

        expect($this->post->fresh()->container_id)->toBe('container_novo');
    });
});

describe('preparação antecipada (warmup)', function () {

    it('cria o container sem publicar', function () {
        Http::fake([INSTAGRAM_GRAPH.'/*' => Http::response(['id' => 'container_novo'])]);

        app(InstagramService::class)->prepare($this->account, $this->post);

        $post = $this->post->fresh();
        expect($post->status)->toBe('pending')
            ->and($post->container_id)->toBe('container_novo');

        Bus::assertDispatched(CheckInstagramContainerJob::class, fn ($job) => ! $job->shouldPublish);
    });

    it('não interrompe o warmup quando o Instagram falha', function () {
        Http::fake([INSTAGRAM_GRAPH.'/*' => Http::response(['error' => ['message' => 'falhou']], 500)]);

        app(InstagramService::class)->prepare($this->account, $this->post);

        expect($this->post->fresh()->status)->toBe('pending');
    });
});
