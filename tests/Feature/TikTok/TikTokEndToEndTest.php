<?php

use App\Jobs\CheckTikTokPostStatusJob;
use App\Jobs\PublishPostJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Roda a esteira real: PublishPostJob (via factory) e depois o CheckTikTokPostStatusJob despachado por ele. */
function tiktokRunPipeline($post): ?CheckTikTokPostStatusJob
{
    app()->call([new PublishPostJob($post), 'handle']);

    $dispatched = null;
    Bus::assertDispatched(CheckTikTokPostStatusJob::class, function ($job) use (&$dispatched) {
        $dispatched = $job;

        return true;
    });

    return $dispatched;
}

describe('ponta a ponta: PublishPostJob -> init -> chunks -> status', function () {

    beforeEach(function () {
        Bus::fake([CheckTikTokPostStatusJob::class]);
        $this->user = createUserWithPermissions(['posts.create', 'posts.view']);
    });

    it('publica vídeo de 1 chunk e termina published com o ID público, limpando o S3', function () {
        tiktokFakeApi(['status_data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [7123456]]]);
        $account = tiktokAccount($this->user);
        $media = tiktokMedia($this->user, 1024);
        $post = tiktokPost($this->user, $account, $media);

        $check = tiktokRunPipeline($post);

        expect($post->fresh()->status)->toBe('processing')
            ->and($post->fresh()->container_id)->toBe('v_pub_file~v2.123');
        Storage::disk('s3')->assertExists($media);

        $check->withFakeQueueInteractions();
        app()->call([$check, 'handle']);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('published')
            ->and($fresh->platform_post_id)->toBe('7123456')
            ->and($fresh->published_at)->not->toBeNull();
        Storage::disk('s3')->assertMissing($media);
    });

    it('publica vídeo multi-chunk e confirma pelo status', function () {
        tiktokFakeApi();
        $account = tiktokAccount($this->user);
        $media = tiktokMedia($this->user, 25 * 1024 * 1024);
        $post = tiktokPost($this->user, $account, $media);

        $check = tiktokRunPipeline($post);
        $puts = Http::recorded()->filter(fn ($p) => $p[0]->method() === 'PUT')->count();
        expect($puts)->toBe(2);

        $check->withFakeQueueInteractions();
        app()->call([$check, 'handle']);

        expect($post->fresh()->status)->toBe('published');
    });

    it('FAILED no status: failed com mensagem pt-BR, S3 mantido e notificação de falha', function () {
        tiktokFakeApi(['status_data' => ['status' => 'FAILED', 'fail_reason' => 'file_format_check_failed']]);
        $account = tiktokAccount($this->user);
        $media = tiktokMedia($this->user);
        $post = tiktokPost($this->user, $account, $media);

        $check = tiktokRunPipeline($post);
        $check->withFakeQueueInteractions();
        app()->call([$check, 'handle']);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->toContain('formato')
            ->and($fresh->error_message)->not->toContain('file_format_check_failed');
        Storage::disk('s3')->assertExists($media);
    });

    it('erro da API de status (token revogado) não deixa o post em processing: vira failed na hora com mensagem mapeada (sem esperar as 15 tentativas)', function () {
        $account = tiktokAccount($this->user);
        $media = tiktokMedia($this->user);
        $post = tiktokPost($this->user, $account, $media, [], ['status' => 'processing', 'container_id' => 'v_pub_file~v2.123']);

        Http::fake([
            'open.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response(
                ['error' => ['code' => 'access_token_invalid', 'message' => 'SEGREDO-CRU', 'log_id' => 'x']], 401
            ),
        ]);

        $check = new CheckTikTokPostStatusJob($post, $account, 'v_pub_file~v2.123');
        $check->withFakeQueueInteractions();

        // Erro permanente: o job marca failed sozinho, sem lançar (nada de retry).
        app()->call([$check, 'handle']);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->toContain('Reconecte')
            ->and($fresh->error_message)->not->toContain('SEGREDO-CRU');
    });

    it('failed() não sobrescreve um post já publicado', function () {
        $account = tiktokAccount($this->user);
        $post = tiktokPost($this->user, $account, tiktokMedia($this->user), [], [
            'status' => 'published', 'platform_post_id' => '1',
        ]);

        (new CheckTikTokPostStatusJob($post, $account, 'x'))->failed(new MaxAttemptsExceededException('x'));

        expect($post->fresh()->status)->toBe('published');
    });
});
