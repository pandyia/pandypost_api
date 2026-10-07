<?php

use App\Exceptions\ScheduledPostException;
use App\Jobs\CheckInstagramContainerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const INSTAGRAM_JOB_GRAPH = 'https://graph.instagram.com/v25.0';

beforeEach(function () {
    Storage::fake('s3');

    $this->account = createSocialAccount('instagram', createUserWithPermissions(), ['platform_id' => '17841400000']);
    $this->post = createScheduledPost($this->account, [
        'status' => 'processing',
        'container_id' => 'container_1',
        'container_created_at' => now(),
    ]);

    Storage::disk('s3')->put($this->post->media_path, 'video');
});

function runInstagramContainerJob($post, $account, bool $shouldPublish = true): CheckInstagramContainerJob
{
    $job = (new CheckInstagramContainerJob($post, $account, 'container_1', $shouldPublish))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

function fakeInstagramContainer(string $status, array $publishResponse = ['id' => 'midia_publicada'], int $publishStatus = 200): void
{
    Http::fake([
        INSTAGRAM_JOB_GRAPH.'/container_1*' => Http::response(['status_code' => $status]),
        INSTAGRAM_JOB_GRAPH.'/17841400000/media_publish' => Http::response($publishResponse, $publishStatus),
        INSTAGRAM_JOB_GRAPH.'/midia_publicada*' => Http::response(['permalink' => 'https://www.instagram.com/reel/AbC123/']),
    ]);
}

describe('acompanhamento do container', function () {

    it('publica quando o container fica pronto, guarda o link e apaga a mídia', function () {
        fakeInstagramContainer('FINISHED');

        runInstagramContainerJob($this->post, $this->account);

        $post = $this->post->fresh();
        expect($post->status)->toBe('published')
            ->and($post->platform_post_id)->toBe('midia_publicada')
            ->and($post->getPlatformPostUrl())->toBe('https://www.instagram.com/reel/AbC123/');

        Storage::disk('s3')->assertMissing($this->post->media_path);
    });

    it('mantém a mídia que outro post pendente ainda usa', function () {
        fakeInstagramContainer('FINISHED');
        createScheduledPost($this->account, ['media_path' => $this->post->media_path]);

        runInstagramContainerJob($this->post, $this->account);

        Storage::disk('s3')->assertExists($this->post->media_path);
    });

    it('no warmup, só registra que o container ficou pronto', function () {
        fakeInstagramContainer('FINISHED');
        $this->post->update(['status' => 'pending']);

        runInstagramContainerJob($this->post, $this->account, shouldPublish: false);

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/media_publish'));
        expect($this->post->fresh()->status)->toBe('pending');
    });

    it('consulta de novo enquanto o container processa', function () {
        fakeInstagramContainer('IN_PROGRESS');

        $job = runInstagramContainerJob($this->post, $this->account);

        $job->assertReleased(delay: 5);
        expect($this->post->fresh()->status)->toBe('processing');
    });

    it('acompanha o container por até 30 minutos', function () {
        $job = new CheckInstagramContainerJob($this->post, $this->account, 'container_1');

        expect($job->retryUntil()->getTimestamp())
            ->toBeBetween(now()->addMinutes(29)->getTimestamp(), now()->addMinutes(31)->getTimestamp());
    });
});

describe('container com erro', function () {

    it('na publicação, descarta o container e falha o job (o failed() marca o post)', function () {
        fakeInstagramContainer('ERROR');

        $job = runInstagramContainerJob($this->post, $this->account);

        $job->assertFailed();
        expect($this->post->fresh()->container_id)->toBeNull();
    });

    it('no warmup, só descarta o container e o post continua pendente', function () {
        fakeInstagramContainer('ERROR');
        $this->post->update(['status' => 'pending']);

        $job = runInstagramContainerJob($this->post, $this->account, shouldPublish: false);

        $job->assertNotFailed();
        $post = $this->post->fresh();
        expect($post->status)->toBe('pending')
            ->and($post->container_id)->toBeNull();
    });
});

describe('falhas na publicação', function () {

    it('lança erro de domínio com o motivo do Instagram quando a publicação é recusada', function () {
        fakeInstagramContainer('FINISHED', ['error' => ['message' => 'Limite de publicações atingido']], 400);

        expect(fn () => runInstagramContainerJob($this->post, $this->account))
            ->toThrow(ScheduledPostException::class, 'Falha ao publicar no Instagram: Limite de publicações atingido');

        expect($this->post->fresh()->status)->toBe('processing');
    });

    it('marca como falho e apaga a mídia quando o prazo de acompanhamento termina', function () {
        $job = new CheckInstagramContainerJob($this->post, $this->account, 'container_1');

        $job->failed(new MaxAttemptsExceededException('expirado'));

        $post = $this->post->fresh();
        expect($post->status)->toBe('failed')
            ->and($post->error_message)->toBe('O Instagram não terminou de processar a mídia dentro do prazo de acompanhamento.');
        Storage::disk('s3')->assertMissing($this->post->media_path);
    });

    it('marca como falho com a mensagem do erro quando a publicação falha repetidamente', function () {
        $job = new CheckInstagramContainerJob($this->post, $this->account, 'container_1');

        $job->failed(ScheduledPostException::publishFailed('Instagram', 'o Instagram não conseguiu processar a mídia.'));

        expect($this->post->fresh()->error_message)
            ->toBe('Falha ao publicar no Instagram: o Instagram não conseguiu processar a mídia.');
    });

    it('no warmup, o fim do prazo não altera o post', function () {
        $this->post->update(['status' => 'pending']);
        $job = new CheckInstagramContainerJob($this->post, $this->account, 'container_1', shouldPublish: false);

        $job->failed(new MaxAttemptsExceededException('expirado'));

        expect($this->post->fresh()->status)->toBe('pending');
        Storage::disk('s3')->assertExists($this->post->media_path);
    });
});
