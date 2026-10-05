<?php

use App\Exceptions\ScheduledPostException;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const TIKTOK_STATUS_URL = 'https://open.tiktokapis.com/v2/post/publish/status/fetch/';

beforeEach(function () {
    Storage::fake('s3');

    $this->account = createTikTokAccount(createUserWithPermissions());
    $this->post = createTikTokPost($this->account, [
        'status' => 'processing',
        'container_id' => 'v_pub_file~123',
        'payload' => ['thumbnail_path' => 'workspaces/x/thumbnails/capa.jpg'],
    ]);

    Storage::disk('s3')->put($this->post->media_path, 'video');
    Storage::disk('s3')->put('workspaces/x/thumbnails/capa.jpg', 'capa');
});

function fakeTikTokStatus(array $data): void
{
    Http::fake([TIKTOK_STATUS_URL => Http::response(['data' => $data, 'error' => ['code' => 'ok']])]);
}

function runTikTokStatusJob(CheckTikTokPostStatusJob $job): CheckTikTokPostStatusJob
{
    $job->withFakeQueueInteractions()->handle(app(StorageService::class));

    return $job;
}

describe('publicação concluída', function () {

    it('marca como publicado com o id público do vídeo e limpa os arquivos', function () {
        fakeTikTokStatus(['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [7_384_910_293_840_192_123]]);

        runTikTokStatusJob(new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123'));

        $post = $this->post->fresh();
        expect($post->status)->toBe('published')
            ->and($post->platform_post_id)->toBe('7384910293840192123')
            ->and($post->published_at)->not->toBeNull()
            ->and($post->getPlatformPostUrl())->toBe('https://www.tiktok.com/@/video/7384910293840192123');

        Storage::disk('s3')->assertMissing($this->post->media_path);
        Storage::disk('s3')->assertMissing('workspaces/x/thumbnails/capa.jpg');

        Http::assertSent(fn (Request $request) => $request->url() === TIKTOK_STATUS_URL
            && $request['publish_id'] === 'v_pub_file~123'
            && $request->hasHeader('Authorization', 'Bearer tiktok_access_token'));
    });

    it('marca como publicado sem link quando o TikTok ainda não liberou o id público', function () {
        fakeTikTokStatus(['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => []]);

        runTikTokStatusJob(new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123'));

        $post = $this->post->fresh();
        expect($post->status)->toBe('published')
            ->and($post->platform_post_id)->toBeNull()
            ->and($post->getPlatformPostUrl())->toBeNull();
    });

    it('mantém o vídeo no storage quando outro post pendente usa o mesmo arquivo', function () {
        createTikTokPost($this->account, ['media_path' => $this->post->media_path, 'status' => 'pending']);
        fakeTikTokStatus(['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [123]]);

        runTikTokStatusJob(new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123'));

        Storage::disk('s3')->assertExists($this->post->media_path);
    });
});

describe('publicação recusada', function () {

    it('marca como falho com o motivo do TikTok e limpa os arquivos', function () {
        fakeTikTokStatus(['status' => 'FAILED', 'fail_reason' => 'video_pull_failed']);

        $job = runTikTokStatusJob(new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123'));

        $post = $this->post->fresh();
        expect($post->status)->toBe('failed')
            ->and($post->error_message)->toBe('Erro no TikTok: video_pull_failed');

        $job->assertNotReleased();
        Storage::disk('s3')->assertMissing($this->post->media_path);
    });
});

describe('publicação em processamento', function () {

    it('consulta de novo mais tarde enquanto o TikTok processa o vídeo', function () {
        fakeTikTokStatus(['status' => 'PROCESSING_UPLOAD']);

        $job = runTikTokStatusJob(new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123'));

        $job->assertReleased(delay: 5);
        expect($this->post->fresh()->status)->toBe('processing');
        Storage::disk('s3')->assertExists($this->post->media_path);
    });

    it('acompanha a publicação por até 1 hora', function () {
        $job = new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123');

        expect($job->retryUntil()->getTimestamp())->toBeBetween(now()->addMinutes(59)->getTimestamp(), now()->addMinutes(61)->getTimestamp());
    });

    it('lança erro de domínio quando a consulta de status é recusada', function () {
        Http::fake([TIKTOK_STATUS_URL => Http::response(['error' => ['code' => 'access_token_invalid']], 401)]);

        expect(fn () => runTikTokStatusJob(new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123')))
            ->toThrow(ScheduledPostException::class, 'não foi possível consultar o status da publicação (access_token_invalid)');
    });
});

describe('acompanhamento esgotado', function () {

    it('marca como falho quando o prazo de acompanhamento termina', function () {
        $job = new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123');

        $job->failed(new MaxAttemptsExceededException('expirado'));

        $post = $this->post->fresh();
        expect($post->status)->toBe('failed')
            ->and($post->error_message)->toBe('O TikTok não confirmou a publicação dentro do prazo de acompanhamento.');
        Storage::disk('s3')->assertMissing($this->post->media_path);
    });

    it('marca como falho com a mensagem do erro quando a consulta falha repetidamente', function () {
        $job = new CheckTikTokPostStatusJob($this->post, $this->account, 'v_pub_file~123');

        $job->failed(ScheduledPostException::publishFailed('TikTok', 'não foi possível consultar o status da publicação (access_token_invalid).'));

        expect($this->post->fresh()->error_message)
            ->toBe('Falha ao publicar no TikTok: não foi possível consultar o status da publicação (access_token_invalid).');
    });
});
