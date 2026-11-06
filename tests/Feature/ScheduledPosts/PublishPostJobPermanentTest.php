<?php

use App\Contracts\SocialMediaServiceInterface;
use App\Exceptions\InstagramException;
use App\Exceptions\ScheduledPostException;
use App\Exceptions\TikTokException;
use App\Exceptions\YouTubeException;
use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use App\Services\Factories\SocialMediaFactory;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Spec 018 RF-1/RF-2/RF-10: erro permanente falha uma vez, sem retry; post fora de pending/processing não é publicado.

function googleError(string $reason): GoogleServiceException
{
    return new GoogleServiceException('{"error":"cru"}', 403, null, [['reason' => $reason, 'message' => 'x']]);
}

/** Factory cujo serviço da rede executa o callback (ou só conta a chamada). */
function jobFactory(?Closure $upload = null): array
{
    $calls = new ArrayObject;
    $service = Mockery::mock(SocialMediaServiceInterface::class);
    $service->shouldReceive('upload')->andReturnUsing(function ($account, $post) use ($upload, $calls) {
        $calls[] = $post->id;
        if ($upload) {
            $upload($post);
        }
    });
    $factory = Mockery::mock(SocialMediaFactory::class);
    $factory->shouldReceive('make')->andReturn($service);

    return [$factory, $calls];
}

function jobPost(string $platform = 'youtube', array $overrides = []): ScheduledPost
{
    $user = createUserWithPermissions(['posts.create']);
    $account = connectedSocialAccount($user, $platform);

    return tiktokPost($user, $account, 'workspaces/w/videos/a.mp4', [], ['platform' => $platform] + $overrides);
}

/** Roda o handle com um job de fila falso e devolve o job. */
function runPublishJob(ScheduledPost $post, SocialMediaFactory $factory): PublishPostJob
{
    $job = new PublishPostJob($post);
    $job->withFakeQueueInteractions();
    $job->handle($factory);

    return $job;
}

describe('PublishPostJob: erro permanente não repete', function () {

    it('falha na hora, uma vez, sem voltar para a fila', function (string $platform, Closure $exception) {
        $post = jobPost($platform);
        [$factory, $calls] = jobFactory(function () use ($exception) {
            throw $exception();
        });

        $job = runPublishJob($post, $factory);

        $job->assertFailed();
        $job->assertNotReleased();
        expect($calls)->toHaveCount(1);
    })->with([
        'youtube cota' => ['youtube', fn () => YouTubeException::fromGoogle(googleError('quotaExceeded'))],
        'youtube limite de upload' => ['youtube', fn () => YouTubeException::fromGoogle(googleError('uploadLimitExceeded'))],
        'youtube forbidden' => ['youtube', fn () => YouTubeException::fromGoogle(googleError('forbidden'))],
        'youtube insufficientPermissions' => ['youtube', fn () => YouTubeException::fromGoogle(googleError('insufficientPermissions'))],
        'youtube authError' => ['youtube', fn () => YouTubeException::fromGoogle(googleError('authError'))],
        'youtube invalid_grant' => ['youtube', fn () => YouTubeException::fromGoogle(googleError('invalid_grant'))],
        'youtube sem canal' => ['youtube', fn () => YouTubeException::fromGoogle(googleError('youtubeSignupRequired'))],
        'youtube refresh falhou' => ['youtube', fn () => YouTubeException::tokenRefreshFailed()],
        'youtube sem refresh_token' => ['youtube', fn () => YouTubeException::refreshTokenMissing()],
        'instagram token' => ['instagram', fn () => InstagramException::fromApiCode(190)],
        'tiktok escopo' => ['tiktok', fn () => TikTokException::scopeMissing()],
        'tiktok privacidade' => ['tiktok', fn () => TikTokException::privacyNotAllowed()],
        'tiktok token' => ['tiktok', fn () => TikTokException::fromApiCode('access_token_invalid')],
        'conta não encontrada' => ['youtube', fn () => ScheduledPostException::accountNotFound()],
        'assinatura inativa' => ['youtube', fn () => ScheduledPostException::subscriptionInactive()],
    ]);

    it('failed() chamado pela fila grava a mensagem da exceção de domínio e o post termina failed uma vez', function () {
        $post = jobPost();
        $post->update(['status' => 'processing']);
        $exception = YouTubeException::fromGoogle(googleError('quotaExceeded'));

        (new PublishPostJob($post))->failed($exception);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->toBe($exception->getMessage())
            ->and($fresh->error_message)->not->toContain('cru');
        expect(DB::table('notifications')->where('type', 'post_failed')->count())->toBe(1); // um aviso de falha, não um por tentativa
    });

    it('roda de ponta a ponta na fila sync: uma tentativa, post failed, sem exceção para fora', function () {
        $post = jobPost();
        [$factory, $calls] = jobFactory(fn () => throw YouTubeException::fromGoogle(googleError('quotaExceeded')));
        app()->instance(SocialMediaFactory::class, $factory);

        PublishPostJob::dispatchSync($post);

        expect($calls)->toHaveCount(1)
            ->and($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->toBe(YouTubeException::fromGoogle(googleError('quotaExceeded'))->getMessage());
    });

    it('erro transitório continua propagando para o retry e não marca failed', function (string $platform, Closure $exception) {
        $post = jobPost($platform);
        [$factory] = jobFactory(function () use ($exception) {
            throw $exception();
        });

        expect(fn () => runPublishJob($post, $factory))->toThrow(Exception::class);

        expect($post->fresh()->status)->toBe('pending');
    })->with([
        'youtube upload falhou' => ['youtube', fn () => YouTubeException::uploadFailed()],
        'youtube desconhecido' => ['youtube', fn () => YouTubeException::fromGoogle(googleError('backendError'))],
        'instagram fora do ar' => ['instagram', fn () => InstagramException::connectionFailed()],
        'tiktok indisponível' => ['tiktok', fn () => TikTokException::apiUnavailable()],
        'exceção genérica' => ['youtube', fn () => new RuntimeException('s3 caiu')],
    ]);

});

describe('PublishPostJob: post fora de pending/processing', function () {

    it('não publica post cancelado, publicado ou falho', function (string $status) {
        $post = jobPost('youtube', ['status' => $status]);
        $factory = Mockery::mock(SocialMediaFactory::class);
        $factory->shouldNotReceive('make');

        (new PublishPostJob($post))->handle($factory);

        expect($post->fresh()->status)->toBe($status);
    })->with(['cancelled', 'published', 'failed']);

    it('retry depois de ter ficado em processing continua publicando', function () {
        $post = jobPost('youtube', ['status' => 'processing']);
        [$factory, $calls] = jobFactory();

        runPublishJob($post, $factory);

        expect($calls)->toHaveCount(1);
    });

    it('failed() nunca sobrescreve post já publicado ou cancelado', function (string $status) {
        $post = jobPost('youtube', ['status' => $status, 'error_message' => null]);

        (new PublishPostJob($post))->failed(new RuntimeException('x'));

        $fresh = $post->fresh();
        expect($fresh->status)->toBe($status)->and($fresh->error_message)->toBeNull();
    })->with(['published', 'cancelled']);

});

describe('PublishPostJob: timeout por rede', function () {

    it('alinha ao supervisor da rede e fica abaixo do retry_after do Redis', function (string $platform, int $timeout) {
        $job = new PublishPostJob(jobPost($platform));

        expect($job->timeout)->toBe($timeout)
            ->and($job->timeout)->toBeLessThan((int) config('queue.connections.redis.retry_after'));
    })->with([['youtube', 300], ['tiktok', 300], ['instagram', 180]]);

});
