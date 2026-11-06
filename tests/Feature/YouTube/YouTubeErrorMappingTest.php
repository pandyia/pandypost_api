<?php

use App\Exceptions\YouTubeException;
use App\Jobs\PublishPostJob;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Roda o upload como a esteira real e devolve [exceção lançada, post após o failed()]. */
function youtubeFailingUpload(int $status, ?string $reason): array
{
    Storage::fake('s3');
    $user = createUserWithPermissions(['posts.create']);
    $account = youtubeAccount($user);
    $post = youtubePost($user, $account, youtubeStoredFile($user, 'video.mp4'));
    youtubeFakeSdk([
        youtubeErrorResponse($status, $reason, '{"segredo":"ya29.token-vazado"} corpo cru do Google'),
    ]);

    try {
        app(YouTubeService::class)->upload($account, $post);
    } catch (Throwable $e) {
        (new PublishPostJob($post))->failed($e);
    }

    return [$e ?? null, $post->fresh()];
}

describe('erros do Google viram mensagem pt-BR', function () {

    it('mapeia cada motivo do Google para o texto do produto', function (string $reason, int $status, string $message) {
        [$exception, $post] = youtubeFailingUpload($status, $reason);

        expect($exception)->toBeInstanceOf(YouTubeException::class)
            ->and($post->status)->toBe('failed')
            ->and($post->error_message)->toBe($message);
    })->with([
        'cota diária' => ['quotaExceeded', 403, 'A cota diária do YouTube foi esgotada. Tente novamente amanhã.'],
        'limite de uploads' => ['uploadLimitExceeded', 400, 'O canal atingiu o limite de uploads do YouTube. Tente novamente mais tarde.'],
        'forbidden' => ['forbidden', 403, 'O YouTube recusou o acesso à conta. Reconecte a conta YouTube e aceite todas as permissões.'],
        'permissão insuficiente' => ['insufficientPermissions', 403, 'O YouTube recusou o acesso à conta. Reconecte a conta YouTube e aceite todas as permissões.'],
        'autenticação' => ['authError', 401, 'O YouTube recusou o acesso à conta. Reconecte a conta YouTube e aceite todas as permissões.'],
        'sem canal' => ['youtubeSignupRequired', 401, 'A conta Google conectada não tem um canal do YouTube. Crie o canal e agende de novo.'],
        'motivo desconhecido' => ['algoNovo', 400, 'O YouTube recusou a publicação. Tente novamente em instantes.'],
    ]);

    it('resposta sem motivo vira o erro genérico', function () {
        [$exception, $post] = youtubeFailingUpload(500, null);

        expect($exception)->toBeInstanceOf(YouTubeException::class)
            ->and($post->error_message)->toBe('O YouTube recusou a publicação. Tente novamente em instantes.');
    });

    it('nem o JSON do Google nem o token chegam ao error_message, à exceção ou ao log', function () {
        $logs = tiktokCaptureLogs();

        [$exception, $post] = youtubeFailingUpload(403, 'quotaExceeded');

        foreach ([$post->error_message, $exception->getMessage(), $logs()] as $text) {
            expect($text)->not->toContain('ya29.token-vazado')->not->toContain('corpo cru')->not->toContain('{');
        }
        expect($logs())->toContain('quotaExceeded');
    });

    it('erro que não é do Google (ex.: S3) não vira mensagem do YouTube', function () {
        Storage::fake('s3');
        $user = createUserWithPermissions(['posts.create']);
        $account = youtubeAccount($user);
        $post = youtubePost($user, $account, 'workspaces/x/videos/inexistente.mp4');
        youtubeFakeSdk([youtubeResumeResponse()]);

        expect(fn () => app(YouTubeService::class)->upload($account, $post))->not->toThrow(YouTubeException::class);
    });
});
