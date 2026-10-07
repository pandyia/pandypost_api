<?php

use App\Exceptions\ScheduledPostException;
use App\Services\Storage\StorageService;
use App\Services\YouTubeService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;

uses(RefreshDatabase::class);

const YOUTUBE_VIDEO_SESSION = 'https://upload.youtube.test/video-session';
const YOUTUBE_THUMB_SESSION = 'https://upload.youtube.test/thumbnail-session';

beforeEach(function () {
    Storage::fake('s3');

    config([
        'services.google.client_id' => 'google_client_id',
        'services.google.client_secret' => 'google_client_secret',
    ]);

    $this->account = createSocialAccount('youtube', createUserWithPermissions());
    $this->post = createScheduledPost($this->account, [
        'title' => 'Meu vídeo',
        'caption' => 'Descrição do vídeo',
        'payload' => ['thumbnail_path' => 'workspaces/x/thumbnails/capa.jpg'],
    ]);

    Storage::disk('s3')->put($this->post->media_path, str_repeat('v', 1000));
    Storage::disk('s3')->put('workspaces/x/thumbnails/capa.jpg', 'capa');
});

/**
 * Simula o upload resumable do YouTube: início da sessão, pedaços (308 até o último) e thumbnail.
 */
function fakeYouTubeUpload(array $overrides = []): ArrayObject
{
    return fakeGoogleApi(function (RequestInterface $request) use ($overrides) {
        $url = (string) $request->getUri();

        foreach ($overrides as $pattern => $response) {
            if (str_contains($url, $pattern)) {
                return $response;
            }
        }

        return match (true) {
            str_contains($url, 'upload/youtube/v3/videos') => googleResponse(headers: ['Location' => YOUTUBE_VIDEO_SESSION]),
            str_contains($url, 'upload/youtube/v3/thumbnails') => googleResponse(headers: ['Location' => YOUTUBE_THUMB_SESSION]),
            $url === YOUTUBE_VIDEO_SESSION => youTubeChunkResponse($request),
            $url === YOUTUBE_THUMB_SESSION => googleResponse(['items' => []]),
        };
    });
}

function youTubeChunkResponse(RequestInterface $request): Response
{
    preg_match('/bytes (\d+)-(\d+)\/(\d+)/', $request->getHeaderLine('Content-Range'), $range);
    [, , $end, $total] = $range;

    return (int) $end === $total - 1
        ? googleResponse(['id' => 'video_123'])
        : googleResponse(status: 308, headers: ['Range' => "bytes=0-{$end}"]);
}

function youTubeRequests(ArrayObject $requests, string $pattern): array
{
    return array_values(array_filter(
        $requests->getArrayCopy(),
        fn (RequestInterface $request) => str_contains((string) $request->getUri(), $pattern),
    ));
}

describe('publicação no YouTube', function () {

    it('envia o vídeo com os metadados do post, publica e limpa os arquivos', function () {
        $requests = fakeYouTubeUpload();
        $this->post->update(['payload' => [
            ...$this->post->payload,
            'youtube_privacy_status' => 'unlisted',
            'youtube_category_id' => '20',
            'youtube_tags' => ['jogos', 'tutorial'],
            'youtube_made_for_kids' => true,
        ]]);

        app(YouTubeService::class)->upload($this->account, $this->post);

        $metadata = json_decode((string) youTubeRequests($requests, 'upload/youtube/v3/videos')[0]->getBody(), true);

        expect($metadata['snippet'])->toBe([
            'categoryId' => '20',
            'description' => 'Descrição do vídeo',
            'tags' => ['jogos', 'tutorial'],
            'title' => 'Meu vídeo',
        ])->and($metadata['status'])->toBe([
            'privacyStatus' => 'unlisted',
            'selfDeclaredMadeForKids' => true,
        ]);

        $post = $this->post->fresh();
        expect($post->status)->toBe('published')
            ->and($post->platform_post_id)->toBe('video_123')
            ->and($post->published_at)->not->toBeNull()
            ->and($post->getPlatformPostUrl())->toBe('https://www.youtube.com/watch?v=video_123');

        Storage::disk('s3')->assertMissing($this->post->media_path);
        Storage::disk('s3')->assertMissing('workspaces/x/thumbnails/capa.jpg');
    });

    it('usa os padrões quando o post não tem opções do YouTube', function () {
        $requests = fakeYouTubeUpload();

        app(YouTubeService::class)->upload($this->account, $this->post);

        $metadata = json_decode((string) youTubeRequests($requests, 'upload/youtube/v3/videos')[0]->getBody(), true);

        expect($metadata['snippet']['categoryId'])->toBe('22')
            ->and($metadata['snippet'])->not->toHaveKey('tags')
            ->and($metadata['status'])->toBe(['privacyStatus' => 'public', 'selfDeclaredMadeForKids' => false]);
    });

    it('envia a thumbnail de vídeos normais', function () {
        $requests = fakeYouTubeUpload();

        app(YouTubeService::class)->upload($this->account, $this->post);

        expect(youTubeRequests($requests, 'upload/youtube/v3/thumbnails/set?videoId=video_123'))->toHaveCount(1)
            ->and((string) youTubeRequests($requests, YOUTUBE_THUMB_SESSION)[0]->getBody())->toBe('capa');
    });

    it('não envia thumbnail para Shorts', function () {
        $requests = fakeYouTubeUpload();
        $this->post->update(['payload' => [...$this->post->payload, 'is_short' => true]]);

        app(YouTubeService::class)->upload($this->account, $this->post);

        expect(youTubeRequests($requests, 'thumbnails'))->toBeEmpty()
            ->and($this->post->fresh()->status)->toBe('published');
    });

    it('publica mesmo quando o envio da thumbnail falha', function () {
        fakeYouTubeUpload(['upload/youtube/v3/thumbnails' => googleResponse(['error' => ['code' => 403]], 403)]);

        app(YouTubeService::class)->upload($this->account, $this->post);

        expect($this->post->fresh()->status)->toBe('published');
    });

    it('envia os pedaços do vídeo com Content-Range em sequência até o fim do arquivo', function () {
        $requests = fakeYouTubeUpload();

        app(YouTubeService::class)->upload($this->account, $this->post);

        $chunks = youTubeRequests($requests, YOUTUBE_VIDEO_SESSION);
        $sentBytes = array_sum(array_map(fn (RequestInterface $chunk) => strlen((string) $chunk->getBody()), $chunks));

        expect($sentBytes)->toBe(1000)
            ->and(end($chunks)->getHeaderLine('Content-Range'))->toEndWith('-999/1000');
    });

    it('usa o token da conta sem renovar enquanto ele é válido', function () {
        $requests = fakeYouTubeUpload();

        app(YouTubeService::class)->upload($this->account, $this->post);

        expect(youTubeRequests($requests, 'oauth2.googleapis.com/token'))->toBeEmpty()
            ->and(youTubeRequests($requests, 'upload/youtube/v3/videos')[0]->getHeaderLine('Authorization'))
            ->toBe('Bearer youtube_access_token');
    });

    it('renova o token vencido antes do upload', function () {
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.renovado', 'expires_in' => 3599])]);
        $this->account->update(['expires_at' => now()->subMinute()]);
        $requests = fakeYouTubeUpload();

        app(YouTubeService::class)->upload($this->account, $this->post);

        expect(youTubeRequests($requests, 'upload/youtube/v3/videos')[0]->getHeaderLine('Authorization'))
            ->toBe('Bearer ya29.renovado');
    });

    it('envia pedaços de 8 MB exatos mesmo com o stream entregando 8 KB por leitura', function () {
        $requests = fakeYouTubeUpload();
        PacketStream::register(20 * 1024 * 1024);
        $this->partialMock(StorageService::class, function ($mock) {
            $mock->shouldReceive('size')->andReturn(20 * 1024 * 1024);
            $mock->shouldReceive('readStream')->andReturn(fopen('packet://video', 'r'));
        });

        app(YouTubeService::class)->upload($this->account, $this->post);

        $chunkSizes = array_map(
            fn (RequestInterface $chunk) => strlen((string) $chunk->getBody()),
            youTubeRequests($requests, YOUTUBE_VIDEO_SESSION),
        );

        expect($chunkSizes)->toBe([8 * 1024 * 1024, 8 * 1024 * 1024, 4 * 1024 * 1024]);
    });

    it('lança erro de domínio com motivo amigável quando o YouTube recusa o vídeo e mantém os arquivos', function () {
        fakeYouTubeUpload(['upload/youtube/v3/videos' => googleResponse(['error' => [
            'code' => 403,
            'message' => 'The request cannot be completed because you have exceeded your quota.',
            'errors' => [['reason' => 'quotaExceeded', 'message' => 'quota']],
        ]], 403)]);

        expect(fn () => app(YouTubeService::class)->upload($this->account, $this->post))
            ->toThrow(ScheduledPostException::class, 'Falha ao publicar no YouTube: a cota diária da API do YouTube acabou. Tente novamente amanhã.');

        expect($this->post->fresh()->status)->toBe('processing');
        Storage::disk('s3')->assertExists($this->post->media_path);
    });
});
