<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Enums\ScheduledPostStatus;
use App\Exceptions\BaseException;
use App\Exceptions\InstagramException;
use App\Exceptions\ScheduledPostException;
use App\Jobs\CheckInstagramContainerJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Instagram\InstagramApi;
use App\Services\Storage\StorageService;
use Exception;
use Illuminate\Support\Facades\Log;

class InstagramService implements SocialMediaServiceInterface
{
    public function __construct(
        private readonly StorageService $storageService,
        private readonly InstagramApi $api,
    ) {}

    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload Instagram. Post ID: {$post->id}");
        $post->update(['status' => ScheduledPostStatus::PROCESSING->value]);

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

            Log::info("Container criado: {$containerId}. Disparando job de verificação (publish: ".($shouldPublish ? 'Sim' : 'Não').')...');
            CheckInstagramContainerJob::dispatch($post, $account, $containerId, $shouldPublish);

        } catch (Exception $e) {
            // Sem getMessage(): exceção que não é de domínio pode trazer URL/token. Só classe e código de domínio.
            $code = $e instanceof BaseException ? $e->getErrorCode() : 'n/a';
            Log::error("Erro no processamento do Instagram (Post {$post->id}, ".$e::class.", código {$code}).");
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
        if (! $post->hasValidContainer()) {
            return false;
        }

        Log::info("Verificando container existente: {$post->container_id}");
        $status = $this->getContainerStatus($post->container_id, $accessToken);

        if ($status === 'PUBLISHED') {
            // Um envio anterior chegou a publicar (a resposta do media_publish se perdeu): não republica.
            $this->markAlreadyPublished($post);

            return true;
        }

        if ($status === 'FINISHED') {
            Log::info('Container já está pronto.');
            if ($shouldPublish) {
                $this->publishContainer($post, $account, $accessToken, $post->container_id);
            }

            return true;
        }

        // EXPIRED: o container passou das 24 h e não aceita mais publicação.
        if ($status === 'ERROR' || $status === 'EXPIRED' || $status === null) {
            Log::warning("Container inválido (status: {$status}). Criando novo...");
            $post->update(['container_id' => null, 'container_created_at' => null]);

            return false; // Precisa criar novo
        }

        Log::info('Container em andamento. Disparando job de verificação recorrente.');
        CheckInstagramContainerJob::dispatch($post, $account, $post->container_id, $shouldPublish);

        return true;
    }

    private function getContainerStatus(string $containerId, string $accessToken): ?string
    {
        try {
            return $this->api->containerStatus($accessToken, $containerId);
        } catch (InstagramException $e) {
            Log::error("Erro ao verificar status do container: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Publica o container informado (o do job, não o que está no post: um job antigo nunca publica o container
     * recriado depois), grava o resultado e, se publicou, apaga a mídia do S3.
     * Resposta de erro da Meta marca o post como failed; falta de resposta propaga para o retry da fila.
     */
    public function publishContainer(ScheduledPost $post, SocialAccount $account, string $accessToken, string $containerId): void
    {
        try {
            $mediaId = $this->api->publish($accessToken, $account->platform_id, $containerId);
        } catch (InstagramException $e) {
            if ($e->isUnavailable()) {
                throw $e;
            }

            // A Meta pode ter publicado e ainda assim devolvido erro (publicação concorrente): confere antes de dar o post como falho.
            if ($this->getContainerStatus($containerId, $accessToken) === 'PUBLISHED') {
                $this->markAlreadyPublished($post);

                return;
            }

            Log::error("Falha ao publicar no Instagram (post {$post->id}): {$e->getMessage()}");
            $post->update(['status' => ScheduledPostStatus::FAILED->value, 'error_message' => $e->getMessage()]);

            return;
        }

        $this->finishPublished($post, $mediaId, $mediaId ? $this->permalinkOrNull($post, $accessToken, $mediaId) : null);
    }

    /**
     * O container está PUBLISHED na Meta mas não temos o id da mídia: o id do container não é o da mídia e não há
     * consulta segura de um para o outro. O post fecha como publicado, sem id e sem link, em vez de falhar com o conteúdo no ar.
     */
    public function markAlreadyPublished(ScheduledPost $post): void
    {
        Log::warning("Post {$post->id}: container já publicado no Instagram. Fechando como publicado, sem id da mídia.");

        $this->finishPublished($post, null, null);
    }

    private function finishPublished(ScheduledPost $post, ?string $mediaId, ?string $permalink): void
    {
        $post->update([
            'status' => ScheduledPostStatus::PUBLISHED->value,
            'platform_post_id' => $mediaId,
            'platform_post_url' => $permalink,
            'published_at' => now(),
            'error_message' => null,
        ]);

        Log::info("Post {$post->id} publicado com sucesso no Instagram! Removendo arquivo do S3...");
        $this->storageService->deleteIfUnused($post->mediaPaths(), $post->id);
    }

    /** A publicação já aconteceu: sem o permalink o post fica publicado, só sem link. */
    private function permalinkOrNull(ScheduledPost $post, string $accessToken, string $mediaId): ?string
    {
        try {
            return $this->api->permalink($accessToken, $mediaId);
        } catch (ScheduledPostException $e) {
            Log::warning("Post {$post->id}: {$e->getMessage()}");

            return null;
        }
    }

    private function createMediaContainer(SocialAccount $account, ScheduledPost $post, string $accessToken): string
    {
        // Gera uma presigned GET URL temporária do S3 para a Graph API do Instagram baixar.
        $mediaUrl = $this->storageService->generateDownloadUrl($post->media_path);

        // Sem legenda o campo não vai (null no corpo é um valor, não ausência, para a Graph API).
        $media = $post->caption !== null && $post->caption !== '' ? ['caption' => $post->caption] : [];

        if ($this->isVideo($post->media_path)) {
            $media['media_type'] = 'REELS';
            $media['video_url'] = $mediaUrl;
        } else {
            $media['image_url'] = $mediaUrl;
        }

        return $this->api->createContainer($accessToken, $account->platform_id, $media);
    }

    private function isVideo(string $path): bool
    {
        return in_array(
            strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ['mp4', 'mov', 'avi', 'mkv'],
            true
        );
    }
}
