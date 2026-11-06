<?php

use App\Exceptions\TikTokException;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const TIKTOK_INIT = '/v2/post/publish/video/init/';
const TIKTOK_CREATOR = '/v2/post/publish/creator_info/query/';
const TIKTOK_STATUS = '/v2/post/publish/status/fetch/';
const TIKTOK_MB = 1024 * 1024;

function tiktokUpload(ScheduledPost $post): void
{
    app(TikTokService::class)->upload($post->socialAccount()->withoutGlobalScopes()->first(), $post);
}

/** @return array<int, Request> */
function tiktokSent(string $needle, ?string $method = null): array
{
    return Http::recorded()
        ->map(fn ($pair) => $pair[0])
        ->filter(fn (Request $r) => str_contains($r->url(), $needle) && ($method === null || $r->method() === $method))
        ->values()->all();
}

function tiktokScenario(array $payload = [], int $bytes = 23, array $accountOverrides = [], array $postOverrides = []): array
{
    $user = createUserWithPermissions(['posts.create', 'posts.view', 'social_accounts.view']);
    $account = tiktokAccount($user, $accountOverrides);
    $media = tiktokMedia($user, $bytes);
    $post = tiktokPost($user, $account, $media, $payload, $postOverrides);

    return [$user, $account, $post, $media];
}

beforeEach(fn () => Bus::fake());

describe('RF-5: upload() com 1 chunk (caminho feliz)', function () {

    it('consulta creator_info, faz init FILE_UPLOAD, envia o arquivo e deixa o post em processing', function () {
        [, , $post, $media] = tiktokScenario(['privacy_level' => 'SELF_ONLY', 'disable_comment' => true, 'disable_duet' => false, 'disable_stitch' => true]);
        tiktokFakeApi();

        tiktokUpload($post);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('processing')
            ->and($fresh->container_id)->toBe('v_pub_file~v2.123')
            ->and($fresh->platform_post_id)->toBeNull()
            ->and($fresh->published_at)->toBeNull();

        expect(tiktokSent(TIKTOK_CREATOR))->toHaveCount(1);

        $init = tiktokSent(TIKTOK_INIT);
        expect($init)->toHaveCount(1);
        expect($init[0]->hasHeader('Authorization', 'Bearer token-valido'))->toBeTrue();
        expect($init[0]->data())->toMatchArray([
            'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => 23, 'chunk_size' => 23, 'total_chunk_count' => 1],
        ]);
        expect($init[0]->data()['post_info'])->toMatchArray([
            'title' => 'Olha esse vídeo!',
            'privacy_level' => 'SELF_ONLY',
            'disable_comment' => true,
            'disable_duet' => false,
            'disable_stitch' => true,
        ]);

        $puts = tiktokSent('open-upload.tiktokapis.com', 'PUT');
        expect($puts)->toHaveCount(1);
        expect($puts[0]->header('Content-Range')[0])->toBe('bytes 0-22/23');
        expect($puts[0]->header('Content-Type')[0])->toStartWith('video/');
        expect($puts[0]->body())->toBe(str_repeat('a', 23));
    });

    it('grava o publish_id e dispara o CheckTikTokPostStatusJob na fila tiktok, só depois de enviar o arquivo', function () {
        [, $account, $post] = tiktokScenario();
        tiktokFakeApi();

        tiktokUpload($post);

        Bus::assertDispatchedTimes(CheckTikTokPostStatusJob::class, 1);
        Bus::assertDispatched(CheckTikTokPostStatusJob::class, fn ($job) => $job->publishId === 'v_pub_file~v2.123'
            && $job->post->id === $post->id
            && $job->account->id === $account->id
            && $job->queue === 'tiktok');
    });

    it('D1: o service só inicia; não consulta o status, não marca published e não espera (sem polling)', function () {
        [, , $post] = tiktokScenario();
        tiktokFakeApi(['status_data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => ['7777']]]);

        $inicio = microtime(true);
        tiktokUpload($post);

        expect(tiktokSent(TIKTOK_STATUS))->toHaveCount(0)
            ->and($post->fresh()->status)->toBe('processing')
            ->and(microtime(true) - $inicio)->toBeLessThan(2.0);
    });

    it('RF-6: o vídeo continua no S3 depois do upload (só o estado final apaga)', function () {
        [, , $post, $media] = tiktokScenario();
        tiktokFakeApi();

        tiktokUpload($post);

        Storage::disk('s3')->assertExists($media);
    });

    it('título: usa a legenda e respeita o limite de 2200 caracteres do TikTok', function () {
        [, , $post] = tiktokScenario([], 23, [], ['caption' => str_repeat('é', 3000)]);
        tiktokFakeApi();

        tiktokUpload($post);

        expect(mb_strlen(tiktokSent(TIKTOK_INIT)[0]->data()['post_info']['title']))->toBe(2200);
    });

    it('repassa a divulgação comercial ao post_info', function () {
        [, , $post] = tiktokScenario(['privacy_level' => 'PUBLIC_TO_EVERYONE', 'brand_content_toggle' => true, 'brand_organic_toggle' => false]);
        tiktokFakeApi();

        tiktokUpload($post);

        expect(tiktokSent(TIKTOK_INIT)[0]->data()['post_info'])->toMatchArray([
            'privacy_level' => 'PUBLIC_TO_EVERYONE',
            'brand_content_toggle' => true,
            'brand_organic_toggle' => false,
        ]);
    });

});

describe('RF-5: upload() multi-chunk', function () {

    it('divide o vídeo em chunks contíguos respeitando 5MB-64MB (último até 128MB) e Content-Range correto', function () {
        $size = 25 * TIKTOK_MB;
        [, , $post] = tiktokScenario([], $size);
        tiktokFakeApi();

        tiktokUpload($post);

        $info = tiktokSent(TIKTOK_INIT)[0]->data()['source_info'];
        $puts = tiktokSent('open-upload.tiktokapis.com', 'PUT');

        expect($info['video_size'])->toBe($size)
            ->and($info['total_chunk_count'])->toBeGreaterThanOrEqual(2)
            ->and($puts)->toHaveCount($info['total_chunk_count']);

        $next = 0;
        foreach ($puts as $i => $put) {
            preg_match('#^bytes (\d+)-(\d+)/(\d+)$#', $put->header('Content-Range')[0], $m);
            [$start, $end, $total] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $length = $end - $start + 1;

            expect($start)->toBe($next, "chunk {$i} deve começar onde o anterior terminou")
                ->and($total)->toBe($size)
                ->and(strlen($put->body()))->toBe($length);

            $isLast = $i === count($puts) - 1;
            expect($length)->toBeGreaterThanOrEqual(5 * TIKTOK_MB)
                ->and($length)->toBeLessThanOrEqual($isLast ? 128 * TIKTOK_MB : 64 * TIKTOK_MB);

            $next = $end + 1;
        }
        expect($next)->toBe($size, 'os chunks cobrem o arquivo inteiro');

        expect($post->fresh()->status)->toBe('processing');
        Bus::assertDispatchedTimes(CheckTikTokPostStatusJob::class, 1);
    });

    it('chunk_size informado no init é o tamanho dos chunks que não são o último', function () {
        [, , $post] = tiktokScenario([], 25 * TIKTOK_MB);
        tiktokFakeApi();

        tiktokUpload($post);

        $info = tiktokSent(TIKTOK_INIT)[0]->data()['source_info'];
        $puts = tiktokSent('open-upload.tiktokapis.com', 'PUT');
        foreach (array_slice($puts, 0, -1) as $put) {
            expect(strlen($put->body()))->toBe($info['chunk_size']);
        }
    });

});

describe('RF-2: conta sem escopo de publicação', function () {

    it('falha com mensagem "reconecte", sem chamar nenhuma API e sem apagar o vídeo', function (?string $scopes) {
        [, , $post, $media] = tiktokScenario([], 23, ['scopes' => $scopes]);
        tiktokFakeApi();

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and(mb_strtolower($fresh->error_message))->toContain('reconecte')
            ->and($fresh->error_message)->toContain('TikTok');
        Http::assertNothingSent();
        Storage::disk('s3')->assertExists($media);
        Bus::assertNotDispatched(CheckTikTokPostStatusJob::class);
    })->with([
        'conta antiga (scopes nulo)' => [null],
        'só escopos de leitura' => ['user.info.basic,video.list'],
    ]);

});

describe('token expirado', function () {

    it('renova o token e usa o novo no creator_info e no init', function () {
        [, $account, $post] = tiktokScenario([], 23, ['expires_at' => now()->subHour()]);
        tiktokFakeApi();

        tiktokUpload($post);

        foreach ([TIKTOK_CREATOR, TIKTOK_INIT] as $endpoint) {
            expect(tiktokSent($endpoint)[0]->hasHeader('Authorization', 'Bearer novo-access-token'))->toBeTrue("{$endpoint} deve usar o token renovado");
        }
        expect($account->fresh()->access_token)->toBe('novo-access-token')
            ->and($post->fresh()->status)->toBe('processing');
    });

});

describe('RF-4/D4: privacy_level na hora de publicar', function () {

    it('privacidade que o creator-info não permite: o post falha, nunca é rebaixado em silêncio', function () {
        [, , $post, $media] = tiktokScenario(['privacy_level' => 'PUBLIC_TO_EVERYONE']);
        tiktokFakeApi(['creator' => ['privacy_level_options' => ['SELF_ONLY']]]);

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and(mb_strtolower($fresh->error_message))->toContain('privacidade')
            ->and($fresh->payload['privacy_level'])->toBe('PUBLIC_TO_EVERYONE');
        expect(tiktokSent(TIKTOK_INIT))->toHaveCount(0);
        expect(tiktokSent('open-upload.tiktokapis.com'))->toHaveCount(0);
        Storage::disk('s3')->assertExists($media);
        Bus::assertNotDispatched(CheckTikTokPostStatusJob::class);
    });

    it('privacy_level permitido passa sem alteração', function () {
        [, , $post] = tiktokScenario(['privacy_level' => 'MUTUAL_FOLLOW_FRIENDS']);
        tiktokFakeApi(['creator' => ['privacy_level_options' => ['MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY']]]);

        tiktokUpload($post);

        expect(tiktokSent(TIKTOK_INIT)[0]->data()['post_info']['privacy_level'])->toBe('MUTUAL_FOLLOW_FRIENDS');
    });

    it('post antigo sem privacy_level no payload falha: não existe valor padrão (SELF_ONLY implícito)', function () {
        [, , $post] = tiktokScenario();
        $post->update(['payload' => []]);
        tiktokFakeApi();

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post->fresh()))->toThrow(TikTokException::class);

        expect($post->fresh()->status)->toBe('failed');
        expect(tiktokSent(TIKTOK_INIT))->toHaveCount(0);
    });

});

describe('duração máxima do vídeo (Decisão da fase TDD 2: sem validação local)', function () {

    it('não valida a duração localmente: mesmo com max_video_post_duration_sec baixo o init é feito', function () {
        [, , $post] = tiktokScenario();
        tiktokFakeApi(['creator' => ['max_video_post_duration_sec' => 1]]);

        tiktokUpload($post);

        expect(tiktokSent(TIKTOK_INIT))->toHaveCount(1)
            ->and($post->fresh()->status)->toBe('processing');
    });

    it('vídeo recusado pelo TikTok por duração vira mensagem pt-BR mapeada, sem o texto cru', function () {
        [, , $post] = tiktokScenario();
        tiktokFakeApi(['init_response' => Http::response(['error' => [
            'code' => 'duration_check_failed', 'message' => 'RAW-DURATION-MSG', 'log_id' => 'L',
        ]], 400)]);

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class);

        expect($post->fresh()->error_message)->toBe(TikTokException::fromApiCode('duration_check_failed')->getMessage())
            ->and($post->fresh()->error_message)->not->toContain('RAW-DURATION-MSG');
    });

});

describe('Decisão da fase TDD 4: quem marca failed', function () {

    it('o service só propaga a TikTokException; não grava failed (o PublishPostJob::failed() faz isso)', function () {
        [, , $post] = tiktokScenario([], 23, ['scopes' => null]);
        tiktokFakeApi();

        expect(fn () => tiktokUpload($post))->toThrow(TikTokException::class);

        expect($post->fresh()->status)->toBe('processing')
            ->and($post->fresh()->error_message)->toBeNull();
    });

    it('PublishPostJob::failed() grava failed com a mensagem pt-BR da exceção e mantém a mídia no S3', function () {
        [, , $post, $media] = tiktokScenario();

        (new PublishPostJob($post))->failed(TikTokException::fromApiCode('spam_risk_too_many_posts'));

        expect($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->toBe(TikTokException::fromApiCode('spam_risk_too_many_posts')->getMessage());
        Storage::disk('s3')->assertExists($media);
    });

});

describe('RF-8: erros da API do TikTok viram pt-BR sem expor o body', function () {

    it('init recusado por app não auditado', function () {
        [, , $post, $media] = tiktokScenario();
        tiktokFakeApi(['init_response' => Http::response(['error' => [
            'code' => 'unaudited_client_can_only_post_to_private_accounts',
            'message' => 'Please review our integration guidelines',
            'log_id' => 'LOG-XYZ-1',
        ]], 403)]);

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class, 'App do TikTok não auditado');

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->toContain('App do TikTok não auditado')
            ->and($fresh->error_message)->not->toContain('Please review')
            ->and($fresh->error_message)->not->toContain('LOG-XYZ-1')
            ->and($fresh->error_message)->not->toContain('unaudited_client');
        Storage::disk('s3')->assertExists($media);
        expect(tiktokSent('open-upload.tiktokapis.com'))->toHaveCount(0);
        Bus::assertNotDispatched(CheckTikTokPostStatusJob::class);
    });

    it('cada código conhecido do init vira a mensagem mapeada, nunca o texto cru do TikTok', function (string $code) {
        [, , $post] = tiktokScenario();
        tiktokFakeApi(['init_response' => Http::response(['error' => [
            'code' => $code, 'message' => 'RAW-ENGLISH-MESSAGE', 'log_id' => 'LOG-RAW-2',
        ]], 400)]);

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class);

        $esperada = TikTokException::fromApiCode($code)->getMessage();
        $message = $post->fresh()->error_message;
        expect($post->fresh()->status)->toBe('failed')
            ->and($message)->toBe($esperada)
            ->and($message)->not->toContain('RAW-ENGLISH-MESSAGE')
            ->and($message)->not->toContain('LOG-RAW-2')
            ->and($message)->not->toContain($code);
    })->with([
        'spam_risk_too_many_posts', 'reached_active_user_cap', 'scope_not_authorized',
        'access_token_invalid', 'url_ownership_unverified', 'file_format_check_failed',
    ]);

    it('creator_info recusando o token (access_token_invalid) falha o post com a mensagem mapeada e não faz o init', function () {
        [, , $post] = tiktokScenario();
        tiktokFakeApi(['creator_response' => Http::response(['data' => [], 'error' => [
            'code' => 'access_token_invalid', 'message' => 'RAW-TOKEN-MSG', 'log_id' => 'L',
        ]], 401)]);

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class);

        expect($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->toBe(TikTokException::fromApiCode('access_token_invalid')->getMessage());
        expect(tiktokSent(TIKTOK_INIT))->toHaveCount(0);
    });

    it('init sem publish_id/upload_url não vaza a resposta do TikTok no erro', function () {
        [, , $post] = tiktokScenario();
        tiktokFakeApi(['init_response' => Http::response(['data' => ['segredo' => 'CORPO-CRU-ABC'], 'error' => ['code' => 'ok', 'message' => '']])]);

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class);

        $message = $post->fresh()->error_message;
        expect($post->fresh()->status)->toBe('failed')
            ->and($message)->not->toBeEmpty()
            ->and($message)->not->toContain('CORPO-CRU-ABC')->not->toContain('segredo')->not->toContain('{');
    });

    it('falha no envio do chunk (PUT) falha o post sem expor o body da resposta e sem disparar o job de status', function () {
        [, , $post, $media] = tiktokScenario();
        tiktokFakeApi(['put_status' => 500, 'put_body' => 'UPLOAD-BODY-SECRETO']);

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class);

        $message = $post->fresh()->error_message;
        expect($post->fresh()->status)->toBe('failed')
            ->and($message)->not->toBeEmpty()
            ->and($message)->not->toContain('UPLOAD-BODY-SECRETO');
        Bus::assertNotDispatched(CheckTikTokPostStatusJob::class);
        Storage::disk('s3')->assertExists($media);
    });

    it('falha definitiva no upload dispara a notificação de falha existente (RF-9)', function () {
        // O beforeEach faz Bus::fake() geral, o que engoliria a notificação em fila; fake só do job de status.
        Bus::fake([CheckTikTokPostStatusJob::class]);
        [$user, , $post] = tiktokScenario();
        tiktokFakeApi(['init_response' => Http::response(['error' => ['code' => 'spam_risk_too_many_posts', 'message' => 'x']], 429)]);

        expect(fn () => tiktokUploadViaJob($post->socialAccount()->withoutGlobalScopes()->first(), $post))->toThrow(TikTokException::class);

        $notifications = Notification::where('type', 'post_failed')->get();
        expect($notifications->pluck('notifiable_id')->all())->toContain($user->id);
    });

});
