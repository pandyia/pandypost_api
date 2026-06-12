<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use App\Services\Storage\StorageService;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Exception;

class YouTubeService implements SocialMediaServiceInterface
{
    private const CHUNK_SIZE = 1 * 1024 * 1024; // 1MB

    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload YouTube. Post ID: {$post->id}");
        $post->update(['status' => 'processing']);

        try {
            $client = $this->getAuthenticatedClient($account);
            $youtube = new YouTube($client);
            $video = $this->createVideoMetadata($post);
            
            $videoId = $this->streamVideoUpload($client, $youtube, $video, $post);

            $payload = $post->payload ?? [];
            
            // O YouTube desqualifica Shorts e os transforma em Vídeos Normais se injetarmos uma Custom Thumbnail via API.
            // Portanto, bloqueamos o envio da thumb caso o post seja marcado como Short.
            $isShort = (bool) Arr::get($payload, 'is_short', false);
            
            $thumbnailPath = Arr::get($payload, 'thumbnail_path');

            if (!$isShort && $thumbnailPath) {
                $this->streamThumbnailUpload($client, $youtube, $videoId, $thumbnailPath);
            }

            $post->update([
                'status' => 'published',
                'platform_post_id' => $videoId,
                'published_at' => now(),
            ]);
            
            Log::info("Post {$post->id} enviado ao YouTube com sucesso! Removendo arquivo do S3...");
            $this->cleanupFiles($post, $payload);
            
        } catch (Exception $e) {
            Log::error("Erro no upload do YouTube para o Post {$post->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function getAuthenticatedClient(SocialAccount $account): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken($account->access_token);

        if ($client->isAccessTokenExpired()) {
            if (!$account->refresh_token) {
                throw new Exception("Token expirado e sem refresh_token disponível.");
            }
            $newTokens = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);
            if (isset($newTokens['access_token'])) {
                $account->update([
                    'access_token' => $newTokens['access_token'],
                    'expires_at' => now()->addSeconds($newTokens['expires_in'] ?? 3600),
                ]);
            }
        }

        return $client;
    }

    private function createVideoMetadata(ScheduledPost $post): Video
    {
        $video = new Video();
        $snippet = new VideoSnippet();
        $snippet->setTitle($post->title);
        
        $description = $post->caption ?? '';
        $payload = $post->payload ?? [];
        
        $snippet->setDescription($description);
        
        // Categoria dinâmica (padrão 22 = People & Blogs)
        $snippet->setCategoryId((string) Arr::get($payload, 'youtube_category_id', '22'));

        // Tags dinâmicas vindas do Frontend (obrigatórias sendo um Array genuíno)
        $tags = Arr::get($payload, 'youtube_tags', []);
        if (is_array($tags) && $tags !== []) {
            $snippet->setTags(array_map('trim', $tags));
        }

        $video->setSnippet($snippet);

        $statusObj = new VideoStatus();
        $privacy = (string) Arr::get($payload, 'youtube_privacy_status', 'public');
        $statusObj->setPrivacyStatus($privacy);
        
        // Regra COPPA de conteúdo para crianças (padrão: false)
        $isForKids = (bool) Arr::get($payload, 'youtube_made_for_kids', false);
        $statusObj->setSelfDeclaredMadeForKids($isForKids);
        
        $video->setStatus($statusObj);

        return $video;
    }

    /**
     * Faz stream do S3 direto para a YouTube API usando resumable upload.
     * Os bytes passam pelo worker em stream (nunca materializa o arquivo em disco).
     */
    private function streamVideoUpload(Client $client, YouTube $youtube, Video $video, ScheduledPost $post): string
    {
        $client->setDefer(true);
        $insertRequest = $youtube->videos->insert('snippet,status', $video);
        $fileSize = $this->storageService->size($post->media_path);
        
        $media = new MediaFileUpload($client, $insertRequest, 'video/*', null, true, self::CHUNK_SIZE);
        $media->setFileSize($fileSize);

        $handle = $this->storageService->readStream($post->media_path);
        $status = $this->processResumableStream($handle, $media);
        
        $client->setDefer(false);

        if (!$status || !isset($status->id)) {
            throw new Exception("Falha ao fazer upload do vídeo para o YouTube.");
        }
        
        return $status->id;
    }

    private function streamThumbnailUpload(Client $client, YouTube $youtube, string $videoId, string $thumbnailPath): void
    {
        try {
            $thumbSize = $this->storageService->size($thumbnailPath);
            $thumbMime = $this->storageService->mimeType($thumbnailPath);
            $thumbHandle = $this->storageService->readStream($thumbnailPath);
            
            $client->setDefer(true);
            $thumbRequest = $youtube->thumbnails->set($videoId);
            $thumbMedia = new MediaFileUpload($client, $thumbRequest, $thumbMime, null, true, self::CHUNK_SIZE);
            $thumbMedia->setFileSize($thumbSize);
            
            $this->processResumableStream($thumbHandle, $thumbMedia);
            
            $client->setDefer(false);
        } catch (Exception $e) {
            Log::warning("Falha ao subir thumbnail pro YouTube: " . $e->getMessage());
        }
    }

    private function cleanupFiles(ScheduledPost $post, array $payload): void
    {
        $paths = [$post->media_path];
        
        $thumbnailPath = Arr::get($payload, 'thumbnail_path');
        if ($thumbnailPath) {
            $paths[] = $thumbnailPath;
        }

        $this->storageService->deleteMany($paths);
    }

    /**
     * Processa a leitura do Stream do S3 e envio em partes (Chunks) para a API.
     * 
     * @param resource $handle
     * @param MediaFileUpload $media
     * @return mixed 
     */
    private function processResumableStream($handle, MediaFileUpload $media)
    {
        $status = false;
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            $status = $media->nextChunk($chunk);
        }
        fclose($handle);
        
        return $status;
    }
}
