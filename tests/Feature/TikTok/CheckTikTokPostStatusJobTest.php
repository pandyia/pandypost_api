<?php

use App\Enums\Exceptions\TikTokError;
use App\Exceptions\TikTokException;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Post em processing (já iniciado pelo service) com vídeo no S3. */
function tiktokProcessing(array $accountOverrides = []): array
{
    $user = createUserWithPermissions(['posts.create', 'posts.view']);
    $account = tiktokAccount($user, $accountOverrides);
    $media = tiktokMedia($user);
    $post = tiktokPost($user, $account, $media, [], ['status' => 'processing', 'container_id' => 'v_pub_file~v2.123']);

    return [$user, $account, $post, $media];
}

function tiktokRunStatusJob(ScheduledPost $post, SocialAccount $account): CheckTikTokPostStatusJob
{
    $job = new CheckTikTokPostStatusJob($post, $account, 'v_pub_file~v2.123');
    $job->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

function tiktokFakeStatus(array $data): void
{
    Http::fake([
        'open.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response(['data' => $data, 'error' => ['code' => 'ok', 'message' => '', 'log_id' => 'l']]),
        'open.tiktokapis.com/v2/oauth/token/*' => Http::response(['access_token' => 'novo-access-token', 'expires_in' => 86400]),
    ]);
    Http::preventStrayRequests();
}

describe('RF-7: PUBLISH_COMPLETE', function () {

    it('marca published com o ID público do vídeo, published_at, e apaga o vídeo do S3', function () {
        [, $account, $post, $media] = tiktokProcessing();
        tiktokFakeStatus(['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => ['7390000000000000001']]);

        tiktokRunStatusJob($post, $account);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('published')
            ->and($fresh->platform_post_id)->toBe('7390000000000000001')
            ->and($fresh->published_at)->not->toBeNull()
            ->and($fresh->error_message)->toBeNull();
        Storage::disk('s3')->assertMissing($media);
    });

    it('com o ID público e o username da conta, getPlatformPostUrl devolve o link do vídeo', function () {
        [, $account, $post] = tiktokProcessing(['username' => 'canal_teste']);
        tiktokFakeStatus(['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => ['7390000000000000001']]);

        tiktokRunStatusJob($post, $account);

        expect($post->fresh()->getPlatformPostUrl())->toBe('https://www.tiktok.com/@canal_teste/video/7390000000000000001');
    });

    it('sandbox/não auditado: sem ID público publica mesmo assim, platform_post_id fica null (não usa o publish_id) e não há link', function () {
        [, $account, $post, $media] = tiktokProcessing();
        tiktokFakeStatus(['status' => 'PUBLISH_COMPLETE']);

        tiktokRunStatusJob($post, $account);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('published')
            ->and($fresh->platform_post_id)->toBeNull()
            ->and($fresh->getPlatformPostUrl())->toBeNull();
        Storage::disk('s3')->assertMissing($media);
    });

    it('lista vazia de IDs públicos também deixa platform_post_id null', function () {
        [, $account, $post] = tiktokProcessing();
        tiktokFakeStatus(['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => []]);

        tiktokRunStatusJob($post, $account);

        expect($post->fresh()->status)->toBe('published')->and($post->fresh()->platform_post_id)->toBeNull();
    });

    it('renova o token expirado antes de consultar o status', function () {
        [, $account, $post] = tiktokProcessing(['expires_at' => now()->subHour()]);
        tiktokFakeStatus(['status' => 'PUBLISH_COMPLETE']);

        tiktokRunStatusJob($post, $account);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'status/fetch') && $r->hasHeader('Authorization', 'Bearer novo-access-token'));
    });

});

describe('RF-7/RF-8: FAILED', function () {

    it('marca failed com mensagem pt-BR mapeada, sem o fail_reason cru, e notifica a falha (RF-9)', function () {
        [$user, $account, $post] = tiktokProcessing();
        tiktokFakeStatus(['status' => 'FAILED', 'fail_reason' => 'file_format_check_failed']);

        tiktokRunStatusJob($post, $account);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->failed_at)->not->toBeNull()
            ->and($fresh->platform_post_id)->toBeNull()
            ->and($fresh->error_message)->toBe(TikTokException::fromApiCode('file_format_check_failed')->getMessage())
            ->and($fresh->error_message)->not->toContain('file_format_check_failed');
        expect(Notification::where('type', 'post_failed')->get()->pluck('notifiable_id')->all())->toContain($user->id);
    });

    it('fail_reason desconhecido vira mensagem genérica em pt-BR, sem vazar o texto do TikTok', function () {
        [, $account, $post] = tiktokProcessing();
        tiktokFakeStatus(['status' => 'FAILED', 'fail_reason' => 'internal_zzz_Stack: at foo.go:12']);

        tiktokRunStatusJob($post, $account);

        $message = $post->fresh()->error_message;
        expect($post->fresh()->status)->toBe('failed')
            ->and($message)->not->toBeEmpty()
            ->and($message)->not->toContain('internal_zzz')->not->toContain('foo.go');
    });

    it('FAILED sem fail_reason também falha com mensagem em pt-BR', function () {
        [, $account, $post] = tiktokProcessing();
        tiktokFakeStatus(['status' => 'FAILED']);

        tiktokRunStatusJob($post, $account);

        expect($post->fresh()->status)->toBe('failed')->and($post->fresh()->error_message)->not->toBeEmpty();
    });

});

describe('em andamento', function () {

    it('não marca nada, mantém o vídeo no S3, não notifica e reagenda a si mesmo', function (string $status) {
        [, $account, $post, $media] = tiktokProcessing();
        tiktokFakeStatus(['status' => $status]);

        $job = tiktokRunStatusJob($post, $account);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('processing')
            ->and($fresh->platform_post_id)->toBeNull()
            ->and($fresh->published_at)->toBeNull();
        Storage::disk('s3')->assertExists($media);
        expect(Notification::count())->toBe(0);
        $job->assertReleased();
    })->with(['PROCESSING_DOWNLOAD', 'PROCESSING_UPLOAD', 'SEND_TO_USER_INBOX']);

});

describe('RF-7: tempo esgotado', function () {

    it('failed() com as tentativas esgotadas marca failed com mensagem de tempo esgotado e notifica', function () {
        [$user, $account, $post] = tiktokProcessing();

        $job = new CheckTikTokPostStatusJob($post, $account, 'v_pub_file~v2.123');
        $job->failed(new MaxAttemptsExceededException('job has been attempted too many times'));

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and(mb_strtolower($fresh->error_message))->toContain('tempo')
            ->and($fresh->error_message)->not->toContain('attempted too many times')
            ->and($fresh->published_at)->toBeNull();
        expect(Notification::where('type', 'post_failed')->get()->pluck('notifiable_id')->all())->toContain($user->id);
    });

    it('o job continua com tries suficientes para ~5 min de processamento (plan §7)', function () {
        [, $account, $post] = tiktokProcessing();
        $job = new CheckTikTokPostStatusJob($post, $account, 'v_pub_file~v2.123');

        expect($job->tries)->toBeGreaterThanOrEqual(15)->and($job->queue)->toBe('tiktok');
    });

});

describe('erro da própria chamada de status', function () {

    it('erro permanente (token inválido / sem escopo) marca failed na hora, com mensagem mapeada, sem retry', function (string $code, string $mensagem) {
        [, $account, $post, $media] = tiktokProcessing();
        Http::fake(['open.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response(['error' => ['code' => $code, 'message' => 'RAW-TEXT', 'log_id' => 'l']], 401)]);
        Http::preventStrayRequests();

        $job = tiktokRunStatusJob($post, $account);

        expect($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->toBe($mensagem)->not->toContain('RAW-TEXT');
        expect($job->released ?? [])->toBeEmpty();
        Storage::disk('s3')->assertExists($media);
    })->with([
        'token inválido' => ['access_token_invalid', TikTokException::fromApiCode('access_token_invalid')->getMessage()],
        'escopo não autorizado' => ['scope_not_authorized', TikTokException::fromApiCode('scope_not_authorized')->getMessage()],
    ]);

    it('erro transitório (ex.: limite de requisições) propaga para o retry da fila e não marca failed', function () {
        [, $account, $post] = tiktokProcessing();
        Http::fake(['open.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response(['error' => ['code' => 'internal_error', 'message' => 'x']], 500)]);
        Http::preventStrayRequests();

        expect(fn () => tiktokRunStatusJob($post, $account))->toThrow(TikTokException::class);
        expect($post->fresh()->status)->toBe('processing');
    });

    it('TikTokError::isPermanent só vale para token/permissão', function () {
        expect(TikTokError::ACCESS_TOKEN_INVALID->isPermanent())->toBeTrue()
            ->and(TikTokError::SCOPE_NOT_AUTHORIZED->isPermanent())->toBeTrue()
            ->and(TikTokError::SCOPE_MISSING->isPermanent())->toBeTrue()
            ->and(TikTokError::UNKNOWN->isPermanent())->toBeFalse()
            ->and(TikTokError::SPAM_RISK_TOO_MANY_POSTS->isPermanent())->toBeFalse()
            ->and(TikTokException::apiUnavailable()->isPermanent())->toBeFalse();
    });

});
