<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Jobs\CheckInstagramContainerJob;
use App\Jobs\PublishPostJob;
use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class InstagramService implements SocialMediaServiceInterface
{
    private const GRAPH_API_VERSION = 'v25.0';
    private const GRAPH_API_BASE_URL = 'https://graph.instagram.com';

    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload Instagram. Post ID: {$post->id}");
        $post->update(['status' => 'processing']);
        
        $this->processContainer($account, $post, true);
    }

    /**
     * Prepara o post (cria container) mas NÃO publica.
     * Usado pelo comando de warmup.
     */
    public function prepare(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("[Warmup] Preparando post {$post->id} para Instagram...");
        $this->processContainer($account, $post, false);
    }

    /**
     * Orquestra a verificação e criação de containers no Instagram.
     */
    private function processContainer(SocialAccount $account, ScheduledPost $post, bool $shouldPublish): void
    {
        try {
            $accessToken = $account->getValidToken();

            if ($this->handleExistingContainer($post, $account, $accessToken, $shouldPublish)) {
                return;
            }

            $containerId = $this->createMediaContainer($account, $post, $accessToken);
            
            $post->update([
                'container_id' => $containerId,
                'container_created_at' => now(),
            ]);

            Log::info("Container criado: {$containerId}. Disparando job de verificação (publish: " . ($shouldPublish ? 'Sim' : 'Não') . ")...");
            CheckInstagramContainerJob::dispatch($post, $account, $containerId, $shouldPublish);

        } catch (Exception $e) {
            Log::error("Erro no processamento do Instagram (Post {$post->id}): {$e->getMessage()}");
            if ($shouldPublish) {
                throw $e;
            }
        }
    }

    /**
     * Avalia um container existente e toma as ações necessárias. Retorna true se a execução puder ser finalizada.
     */
    private function handleExistingContainer(ScheduledPost $post, SocialAccount $account, string $accessToken, bool $shouldPublish): bool
    {
        if (!$post->hasValidContainer()) {
            return false;
        }

        Log::info("Verificando container existente: {$post->container_id}");
        $status = $this->getContainerStatus($post->container_id, $accessToken);
        
        if ($status === 'FINISHED') {
            Log::info("Container já está pronto.");
            if ($shouldPublish) {
                $this->publishContainer($post, $account, $accessToken);
            }
            return true;
        }
        
        if ($status === 'ERROR' || $status === null) {
            Log::warning("Container inválido (status: {$status}). Criando novo...");
            $post->update(['container_id' => null, 'container_created_at' => null]);
            return false; // Precisa criar novo
        }

        Log::info("Container em andamento. Disparando job de verificação recorrente.");
        CheckInstagramContainerJob::dispatch($post, $account, $post->container_id, $shouldPublish);
        return true;
    }

    private function getContainerStatus(string $containerId, string $accessToken): ?string
    {
        try {
            $response = Http::get($this->buildApiUrl($containerId), [
                'fields' => 'status_code',
                'access_token' => $accessToken,
            ])->json();

            return $response['status_code'] ?? null;
        } catch (Exception $e) {
            Log::error("Erro ao verificar status do container: {$e->getMessage()}");
            return null;
        }
    }

    private function publishContainer(ScheduledPost $post, SocialAccount $account, string $accessToken): void
    {
        $response = Http::post($this->buildApiUrl("{$account->platform_id}/media_publish"), [
            'creation_id' => $post->container_id,
            'access_token' => $accessToken,
        ])->json();

        if (isset($response['error'])) {
            $errorMessage = json_encode($response);
            Log::error("Falha ao publicar no Instagram. Erro: {$errorMessage}");
            $post->update([
                'status' => 'failed',
                'error_message' => 'Erro na publicação: ' . $errorMessage,
            ]);
            return;
        }

        $post->update([
            'status' => 'published',
            'platform_post_id' => $response['id'] ?? null,
            'published_at' => now(),
        ]);

        $post->user->subscription?->increment('posts_used');

        Log::info("Post {$post->id} publicado com sucesso no Instagram! Removendo arquivo do S3...");
        $this->cleanupFiles($post);
    }

    private function createMediaContainer(SocialAccount $account, ScheduledPost $post, string $accessToken): string
    {
        // Gera uma presigned GET URL temporária do S3 para a Graph API do Instagram baixar.
        $mediaUrl = $this->storageService->generateDownloadUrl($post->media_path);

        $payload = [
            'caption' => $post->caption,
            'access_token' => $accessToken,
        ];

        if ($this->isVideo($post->media_path)) {
            $payload['media_type'] = 'REELS';
            $payload['video_url'] = $mediaUrl;
        } else {
            $payload['image_url'] = $mediaUrl;
        }

        $response = Http::post($this->buildApiUrl("{$account->platform_id}/media"), $payload)->json();

        if (!isset($response['id'])) {
            $error = json_encode($response);
            Log::error("Falha ao criar container Instagram. Retorno: {$error}");
            throw new Exception("Erro ao criar container: {$error}");
        }

        return $response['id'];
    }

    private function buildApiUrl(string $endpoint): string
    {
        return self::GRAPH_API_BASE_URL . '/' . self::GRAPH_API_VERSION . '/' . $endpoint;
    }

    private function isVideo(string $path): bool
    {
        return in_array(
            strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ['mp4', 'mov', 'avi', 'mkv'],
            true
        );
    }

    private function cleanupFiles(ScheduledPost $post): void
    {
        $paths = [$post->media_path];
        
        $payload = $post->payload ?? [];
        if (!empty($payload['thumbnail_path'])) {
            $paths[] = $payload['thumbnail_path'];
        }

        $this->storageService->deleteMany($paths);
    }
}
