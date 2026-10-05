<?php

use App\Exceptions\ScheduledPostException;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Jobs\PublishPostJob;
use App\Services\Storage\StorageService;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const TIKTOK_INIT_URL = 'https://open.tiktokapis.com/v2/post/publish/video/init/';
const TIKTOK_UPLOAD_URL = 'https://open-upload.tiktokapis.com/video/?upload_id=123';
const MEGABYTE = 1024 * 1024;

/**
 * Stream que entrega no máximo 8 KB por leitura, como o stream de rede do S3.
 */
final class PacketStream
{
    public static int $size = 0;

    public $context;

    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        $length = min($count, 8192, self::$size - $this->position);
        $this->position += $length;

        return str_repeat('v', $length);
    }

    public function stream_eof(): bool
    {
        return $this->position >= self::$size;
    }

    public function stream_stat(): array
    {
        return [];
    }
}

beforeEach(function () {
    Bus::fake();
    Storage::fake('s3');

    $this->account = createTikTokAccount(createUserWithPermissions());
    $this->post = createTikTokPost($this->account);
});

function fakeTikTokInit(array $response = []): void
{
    Http::fake([
        TIKTOK_INIT_URL => Http::response($response ?: [
            'data' => ['publish_id' => 'v_pub_file~123', 'upload_url' => TIKTOK_UPLOAD_URL],
            'error' => ['code' => 'ok', 'message' => ''],
        ]),
        TIKTOK_UPLOAD_URL => Http::response([], 201),
    ]);
}

function tiktokChunkRequests(): array
{
    return Http::recorded(fn (Request $request) => $request->method() === 'PUT')
        ->map(fn (array $pair) => $pair[0])
        ->values()
        ->all();
}

describe('envio do vídeo', function () {

    it('envia vídeo pequeno em um único pedaço e passa a acompanhar a publicação', function () {
        fakeTikTokInit();
        Storage::disk('s3')->put($this->post->media_path, str_repeat('v', 23));

        app(TikTokService::class)->upload($this->account, $this->post);

        Http::assertSent(fn (Request $request) => $request->url() === TIKTOK_INIT_URL
            && $request->hasHeader('Authorization', 'Bearer tiktok_access_token')
            && $request['source_info'] === [
                'source' => 'FILE_UPLOAD',
                'video_size' => 23,
                'chunk_size' => 23,
                'total_chunk_count' => 1,
            ]
            && $request['post_info'] === [
                'title' => 'Meu vídeo no TikTok',
                'privacy_level' => 'PUBLIC_TO_EVERYONE',
                'disable_comment' => false,
                'disable_duet' => false,
                'disable_stitch' => false,
            ]);

        $chunks = tiktokChunkRequests();
        expect($chunks)->toHaveCount(1)
            ->and($chunks[0]->header('Content-Range')[0])->toBe('bytes 0-22/23')
            ->and($chunks[0]->header('Content-Type')[0])->toBe('video/mp4')
            ->and(strlen($chunks[0]->body()))->toBe(23);

        $post = $this->post->fresh();
        expect($post->status)->toBe('processing')
            ->and($post->container_id)->toBe('v_pub_file~123');

        Bus::assertDispatched(CheckTikTokPostStatusJob::class, fn (CheckTikTokPostStatusJob $job) => $job->publishId === 'v_pub_file~123'
            && $job->post->is($this->post)
            && $job->account->is($this->account));
    });

    it('divide vídeo grande em pedaços de 10 MEGABYTE e o último absorve o resto, mesmo com o stream entregando 8 KB por leitura', function () {
        fakeTikTokInit();

        if (! in_array('packet', stream_get_wrappers(), true)) {
            stream_wrapper_register('packet', PacketStream::class);
        }
        PacketStream::$size = 65 * MEGABYTE;

        $this->mock(StorageService::class, function ($mock) {
            $mock->shouldReceive('size')->andReturn(65 * MEGABYTE);
            $mock->shouldReceive('readStream')->andReturn(fopen('packet://video', 'r'));
        });

        app(TikTokService::class)->upload($this->account, $this->post);

        Http::assertSent(fn (Request $request) => $request->url() === TIKTOK_INIT_URL
            && $request['source_info']['chunk_size'] === 10 * MEGABYTE
            && $request['source_info']['total_chunk_count'] === 6);

        $chunks = tiktokChunkRequests();
        $lastByte = 65 * MEGABYTE - 1;

        expect($chunks)->toHaveCount(6)
            ->and($chunks[0]->header('Content-Range')[0])->toBe('bytes 0-'.(10 * MEGABYTE - 1).'/'. 65 * MEGABYTE)
            ->and(strlen($chunks[0]->body()))->toBe(10 * MEGABYTE)
            ->and($chunks[5]->header('Content-Range')[0])->toBe('bytes '. 50 * MEGABYTE."-{$lastByte}/". 65 * MEGABYTE)
            ->and(strlen($chunks[5]->body()))->toBe(15 * MEGABYTE);
    });

    it('envia o MIME correspondente à extensão do vídeo', function (string $extension, string $mimeType) {
        fakeTikTokInit();
        $this->post->update(['media_path' => str_replace('.mp4', ".{$extension}", $this->post->media_path)]);
        Storage::disk('s3')->put($this->post->media_path, 'conteudo');

        app(TikTokService::class)->upload($this->account, $this->post);

        expect(tiktokChunkRequests()[0]->header('Content-Type')[0])->toBe($mimeType);
    })->with([
        'mp4' => ['mp4', 'video/mp4'],
        'mov' => ['mov', 'video/quicktime'],
        'webm' => ['webm', 'video/webm'],
    ]);

    it('envia as opções do TikTok escolhidas no agendamento', function () {
        fakeTikTokInit();
        $this->post->update(['payload' => [
            'tiktok_privacy_level' => 'SELF_ONLY',
            'tiktok_disable_comment' => true,
            'tiktok_disable_duet' => true,
            'tiktok_disable_stitch' => false,
            'tiktok_brand_content_toggle' => true,
        ]]);
        Storage::disk('s3')->put($this->post->media_path, 'conteudo');

        app(TikTokService::class)->upload($this->account, $this->post);

        Http::assertSent(fn (Request $request) => $request->url() === TIKTOK_INIT_URL
            && $request['post_info']['privacy_level'] === 'SELF_ONLY'
            && $request['post_info']['disable_comment'] === true
            && $request['post_info']['disable_duet'] === true
            && $request['post_info']['disable_stitch'] === false
            && $request['post_info']['brand_content_toggle'] === true);
    });
});

describe('falhas no envio', function () {

    it('traduz os erros conhecidos do TikTok e não acompanha a publicação', function (string $code, string $reason) {
        fakeTikTokInit(['error' => ['code' => $code, 'message' => 'mensagem do TikTok']]);
        Storage::disk('s3')->put($this->post->media_path, 'conteudo');

        expect(fn () => app(TikTokService::class)->upload($this->account, $this->post))
            ->toThrow(function (ScheduledPostException $e) use ($reason) {
                expect($e->getMessage())->toBe("Falha ao publicar no TikTok: {$reason}")
                    ->and(strlen($e->getMessage()))->toBeLessThanOrEqual(255);
            });

        expect(tiktokChunkRequests())->toBeEmpty();
        Bus::assertNotDispatched(CheckTikTokPostStatusJob::class);
    })->with([
        ['unaudited_client_can_only_post_to_private_accounts', 'app ainda não auditado pelo TikTok. Até a aprovação, só é possível publicar em contas privadas.'],
        ['privacy_level_option_mismatch', 'a privacidade escolhida não está disponível para esta conta.'],
        ['spam_risk_too_many_posts', 'a conta atingiu o limite diário de publicações. Tente novamente mais tarde.'],
        ['spam_risk_user_banned_from_posting', 'a conta está impedida de publicar pelo TikTok.'],
        ['reached_active_user_cap', 'o app atingiu o limite diário de criadores do TikTok. Tente novamente mais tarde.'],
        ['access_token_invalid', 'o acesso à conta expirou ou foi revogado. Reconecte a conta.'],
        ['scope_not_authorized', 'a conta não autorizou a publicação de vídeos. Reconecte a conta e autorize todas as permissões.'],
        ['rate_limit_exceeded', 'limite de requisições do TikTok atingido. Tente novamente em instantes.'],
        ['invalid_param', 'mensagem do TikTok (invalid_param)'],
    ]);

    it('falha quando o TikTok recusa um pedaço do vídeo', function () {
        Http::fake([
            TIKTOK_INIT_URL => Http::response([
                'data' => ['publish_id' => 'v_pub_file~123', 'upload_url' => TIKTOK_UPLOAD_URL],
                'error' => ['code' => 'ok'],
            ]),
            TIKTOK_UPLOAD_URL => Http::response('erro', 500),
        ]);
        Storage::disk('s3')->put($this->post->media_path, 'conteudo');

        expect(fn () => app(TikTokService::class)->upload($this->account, $this->post))
            ->toThrow(ScheduledPostException::class, 'Falha ao publicar no TikTok: o TikTok recusou o envio do vídeo (HTTP 500).');

        expect($this->post->fresh()->container_id)->toBeNull();
        Bus::assertNotDispatched(CheckTikTokPostStatusJob::class);
    });

    it('marca o post como falho com a mensagem ao esgotar as tentativas de publicação', function () {
        Storage::disk('s3')->put($this->post->media_path, 'conteudo');
        $exception = ScheduledPostException::publishFailed('TikTok', 'a privacidade escolhida não está disponível para esta conta.');

        (new PublishPostJob($this->post))->failed($exception);

        $post = $this->post->fresh();
        expect($post->status)->toBe('failed')
            ->and($post->error_message)->toBe('Falha ao publicar no TikTok: a privacidade escolhida não está disponível para esta conta.');
        Storage::disk('s3')->assertMissing($this->post->media_path);
    });
});
