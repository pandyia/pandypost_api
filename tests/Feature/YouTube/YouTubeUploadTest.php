<?php

use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\YouTubeService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const YT_MB = 1024 * 1024;

/** @return array{0: User, 1: SocialAccount, 2: ScheduledPost} */
function youtubeScenario(array $payload = [], int $bytes = 100): array
{
    Storage::fake('s3');
    $user = createUserWithPermissions(['posts.create']);
    $account = youtubeAccount($user);
    $post = youtubePost($user, $account, youtubeStoredFile($user, 'video.mp4', $bytes), $payload);

    return [$user, $account, $post];
}

function youtubeUpload($account, $post): void
{
    app(YouTubeService::class)->upload($account, $post);
}

/** Requisições enviadas pelo SDK, como [método, url]. */
function youtubeSent(ArrayObject $history): array
{
    return collect($history)->map(fn ($h) => [$h['request']->getMethod(), (string) $h['request']->getUri()])->all();
}

describe('upload do YouTube (caracterização)', function () {

    it('publica o vídeo: grava o ID, published_at e limpa o S3', function () {
        [, $account, $post] = youtubeScenario();
        $history = youtubeFakeSdk([youtubeResumeResponse(), youtubeDoneResponse('abc123')]);

        youtubeUpload($account, $post);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('published')
            ->and($fresh->platform_post_id)->toBe('abc123')
            ->and($fresh->published_at)->not->toBeNull();
        Storage::disk('s3')->assertMissing($post->media_path);
        expect($history)->toHaveCount(2);
    });

    it('envia o vídeo em chunks de 1 MB', function () {
        [, $account, $post] = youtubeScenario(bytes: (int) (2.5 * YT_MB));
        $history = youtubeFakeSdk([
            youtubeResumeResponse(),
            new PsrResponse(308, ['Range' => 'bytes=0-'.(YT_MB - 1)]),
            new PsrResponse(308, ['Range' => 'bytes=0-'.(2 * YT_MB - 1)]),
            youtubeDoneResponse('grande'),
        ]);

        youtubeUpload($account, $post);

        $ranges = collect($history)->skip(1)->map(fn ($h) => $h['request']->getHeaderLine('content-range'))->values()->all();
        $total = (int) (2.5 * YT_MB);
        expect($ranges)->toBe([
            'bytes 0-'.(YT_MB - 1)."/{$total}",
            'bytes '.YT_MB.'-'.(2 * YT_MB - 1)."/{$total}",
            'bytes '.(2 * YT_MB).'-'.($total - 1)."/{$total}",
        ])->and($post->fresh()->platform_post_id)->toBe('grande');
    });

    it('marca o post como processing antes de enviar', function () {
        [, $account, $post] = youtubeScenario();
        youtubeFakeSdk([youtubeErrorResponse(500, null)]);

        expect(fn () => youtubeUpload($account, $post))->toThrow(Exception::class);

        expect($post->fresh()->status)->toBe('processing');
    });

    it('autentica o upload com o token guardado, sem renovar enquanto o expires_at da conta vale', function () {
        [, $account, $post] = youtubeScenario();
        $history = youtubeFakeSdk([youtubeResumeResponse(), youtubeDoneResponse()]);

        youtubeUpload($account, $post);

        expect(collect($history)->first()['request']->getHeaderLine('Authorization'))->toBe('Bearer ya29.token-valido');
    });
});

describe('thumbnail do YouTube (caracterização)', function () {

    it('envia a thumbnail de vídeo normal e limpa vídeo e thumbnail do S3', function () {
        [$user, $account, $post] = youtubeScenario();
        $thumb = youtubeStoredFile($user, 'thumb.jpg', 50);
        $post->update(['payload' => ['thumbnail_path' => $thumb]]);
        $history = youtubeFakeSdk([
            youtubeResumeResponse(), youtubeDoneResponse('v1'),
            youtubeResumeResponse(), new PsrResponse(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        youtubeUpload($account, $post->fresh());

        expect(collect(youtubeSent($history))->contains(fn ($r) => str_contains($r[1], 'thumbnails/set')))->toBeTrue()
            ->and($post->fresh()->status)->toBe('published');
        Storage::disk('s3')->assertMissing($thumb);
        Storage::disk('s3')->assertMissing($post->media_path);
    });

    it('NÃO envia a thumbnail de um Short', function () {
        [$user, $account, $post] = youtubeScenario();
        $thumb = youtubeStoredFile($user, 'thumb.jpg', 50);
        $post->update(['payload' => ['thumbnail_path' => $thumb, 'is_short' => true]]);
        $history = youtubeFakeSdk([youtubeResumeResponse(), youtubeDoneResponse('short1')]);

        youtubeUpload($account, $post->fresh());

        expect($history)->toHaveCount(2)
            ->and($post->fresh()->status)->toBe('published');
    });

    it('falha da thumbnail não derruba o post', function () {
        [$user, $account, $post] = youtubeScenario();
        $thumb = youtubeStoredFile($user, 'thumb.jpg', 50);
        $post->update(['payload' => ['thumbnail_path' => $thumb]]);
        youtubeFakeSdk([
            youtubeResumeResponse(), youtubeDoneResponse('v2'),
            youtubeErrorResponse(400, 'invalidImage'),
        ]);

        youtubeUpload($account, $post->fresh());

        expect($post->fresh()->status)->toBe('published')
            ->and($post->fresh()->platform_post_id)->toBe('v2');
    });
});

describe('falha do upload do YouTube (caracterização)', function () {

    it('erro do Google propaga, o post fica processing e PublishPostJob::failed grava failed com o S3 intacto', function () {
        [, $account, $post] = youtubeScenario();
        youtubeFakeSdk([youtubeErrorResponse(403, 'quotaExceeded')]);

        try {
            youtubeUpload($account, $post);
            $this->fail('deveria lançar exceção');
        } catch (Throwable $e) {
            expect($post->fresh()->status)->toBe('processing');
            (new PublishPostJob($post))->failed($e);
        }

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->not->toBeEmpty();
        Storage::disk('s3')->assertExists($post->media_path);
    });

    it('resposta final sem id vira YouTubeException de upload falho', function () {
        [, $account, $post] = youtubeScenario();
        youtubeFakeSdk([youtubeResumeResponse(), new PsrResponse(200, ['Content-Type' => 'application/json'], '{}')]);

        expect(fn () => youtubeUpload($account, $post))
            ->toThrow(Exception::class, 'Falha ao fazer upload do vídeo para o YouTube.');
        expect($post->fresh()->status)->toBe('processing');
    });
});
