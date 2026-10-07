<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Enums\YouTubePrivacyStatus;
use App\Exceptions\ScheduledPostException;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
use Closure;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Exception as GoogleException;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Throwable;

class YouTubeService implements SocialMediaServiceInterface
{
    // Upload resumable: o Google pede pedaços múltiplos de 256 KB (exceto o último).
    private const CHUNK_SIZE = 8 * 1024 * 1024;

    private const DEFAULT_CATEGORY_ID = '22'; // Pessoas e blogs

    // Motivos de erro do YouTube → explicação exibida ao usuário.
    private const ERROR_MESSAGES = [
        'quotaExceeded' => 'a cota diária da API do YouTube acabou. Tente novamente amanhã.',
        'uploadLimitExceeded' => 'o canal atingiu o limite de envios do YouTube. Tente novamente mais tarde.',
        'authError' => 'o acesso ao canal expirou ou foi revogado. Reconecte a conta.',
        'forbidden' => 'o canal não permite esse envio. Verifique se ele está ativo e verificado.',
    ];

    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    // Envia vídeo e thumbnail, marca o post como publicado e apaga a mídia do S3.
    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload YouTube. Post ID: {$post->id}");
        $post->update(['status' => ScheduledPostStatus::PROCESSING->value]);

        $client = $this->clientFor($account);
        $youtube = new YouTube($client);

        $videoId = $this->uploadVideo($client, $youtube, $post);
        $this->uploadThumbnail($client, $youtube, $videoId, $post);

        $post->markAsPublished($videoId);
        $this->storageService->deletePostMedia($post);

        Log::info("Post {$post->id} publicado no YouTube (vídeo {$videoId}).");
    }

    // Client do Google com o token da conta, renovado se estiver perto de vencer.
    public function clientFor(SocialAccount $account): Client
    {
        $client = app(Client::class);
        $client->setAccessToken($account->getValidToken());

        return $client;
    }

    private function uploadVideo(Client $client, YouTube $youtube, ScheduledPost $post): string
    {
        try {
            $video = $this->resumableUpload(
                $client,
                fn () => $youtube->videos->insert('snippet,status', $this->buildVideo($post)),
                $post->media_path,
                'video/*',
            );
        } catch (GoogleException $e) {
            throw ScheduledPostException::publishFailed(Platform::YOUTUBE->label(), $this->describeError($e));
        }

        if (! isset($video->id)) {
            throw ScheduledPostException::publishFailed(Platform::YOUTUBE->label(), 'o YouTube não confirmou o envio do vídeo.');
        }

        return $video->id;
    }

    // Monta título, descrição e opções escolhidas no agendamento.
    private function buildVideo(ScheduledPost $post): Video
    {
        $payload = $post->payload ?? [];

        $snippet = new VideoSnippet;
        $snippet->setTitle($post->title);
        $snippet->setDescription($post->caption ?? '');
        $snippet->setCategoryId((string) Arr::get($payload, 'youtube_category_id', self::DEFAULT_CATEGORY_ID));

        if ($tags = Arr::get($payload, 'youtube_tags')) {
            $snippet->setTags($tags);
        }

        $status = new VideoStatus;
        $status->setPrivacyStatus(Arr::get($payload, 'youtube_privacy_status', YouTubePrivacyStatus::PUBLIC->value));
        $status->setSelfDeclaredMadeForKids((bool) Arr::get($payload, 'youtube_made_for_kids', false));

        $video = new Video;
        $video->setSnippet($snippet);
        $video->setStatus($status);

        return $video;
    }

    // Short não recebe capa (o YouTube o viraria vídeo normal); falha aqui não impede a publicação.
    private function uploadThumbnail(Client $client, YouTube $youtube, string $videoId, ScheduledPost $post): void
    {
        $thumbnailPath = Arr::get($post->payload ?? [], 'thumbnail_path');

        if (! $thumbnailPath || Arr::get($post->payload, 'is_short', false)) {
            return;
        }

        try {
            $this->resumableUpload(
                $client,
                fn () => $youtube->thumbnails->set($videoId),
                $thumbnailPath,
                $this->storageService->mimeType($thumbnailPath),
            );
        } catch (Throwable $e) {
            Log::warning("Falha ao enviar a thumbnail do vídeo {$videoId} ao YouTube: {$e->getMessage()}");
        }
    }

    /**
     * Envia um arquivo do S3 em pedaços, sem carregar o arquivo inteiro na memória.
     *
     * @param  Closure(): RequestInterface  $buildRequest  chamada à API que vira a requisição de upload
     */
    private function resumableUpload(Client $client, Closure $buildRequest, string $path, string $mimeType): mixed
    {
        // Com defer, a chamada devolve a requisição em vez de executá-la, para enviar em pedaços.
        $client->setDefer(true);
        $request = $buildRequest();
        $client->setDefer(false);

        $media = new MediaFileUpload($client, $request, $mimeType, null, true, self::CHUNK_SIZE);
        $media->setFileSize($this->storageService->size($path));

        $stream = $this->storageService->readStream($path);

        try {
            $result = false;

            while ($result === false && ! feof($stream)) {
                // stream_get_contents lê o pedaço inteiro; fread pararia no primeiro pacote do stream do S3.
                $result = $media->nextChunk(stream_get_contents($stream, self::CHUNK_SIZE));
            }

            return $result;
        } finally {
            fclose($stream);
        }
    }

    // Traduz o motivo do erro do YouTube para uma mensagem que o usuário entende.
    private function describeError(GoogleException $e): string
    {
        $error = $e->getErrors()[0] ?? [];

        return self::ERROR_MESSAGES[$error['reason'] ?? ''] ?? ($error['message'] ?? 'erro inesperado no envio do vídeo.');
    }
}
