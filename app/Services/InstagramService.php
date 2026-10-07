<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Exceptions\ScheduledPostException;
use App\Jobs\CheckInstagramContainerJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

// Publica em duas etapas: cria um container com a mídia e publica quando o Instagram termina de processá-lo.
// O warmup cria o container até 1 h antes, para o post sair na hora certa.
class InstagramService implements SocialMediaServiceInterface
{
    public const GRAPH_API_URL = 'https://graph.instagram.com/v25.0';

    public const CONTAINER_FINISHED = 'FINISHED';

    public const CONTAINER_ERROR = 'ERROR';

    // Extensões publicadas como Reels; as demais vão como imagem.
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv'];

    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload Instagram. Post ID: {$post->id}");
        $post->update(['status' => ScheduledPostStatus::PROCESSING->value]);

        $this->processContainer($account, $post, publish: true);
    }

    // Warmup: cria o container antes do horário, sem publicar; se falhar, a publicação cria outro.
    public function prepare(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("[Warmup] Preparando post {$post->id} para Instagram...");

        try {
            $this->processContainer($account, $post, publish: false);
        } catch (Throwable $e) {
            Log::error("[Warmup] Erro ao preparar o post {$post->id}: {$e->getMessage()}");
        }
    }

    // Status do container no Instagram; FINISHED quer dizer pronto para publicar.
    public function containerStatus(string $containerId, string $accessToken): ?string
    {
        return Http::get($this->url($containerId), [
            'fields' => 'status_code',
            'access_token' => $accessToken,
        ])->json('status_code');
    }

    // Publica o container pronto, guarda o link do post e apaga a mídia do S3.
    public function publish(ScheduledPost $post, SocialAccount $account, string $accessToken): void
    {
        $response = Http::post($this->url("{$account->platform_id}/media_publish"), [
            'creation_id' => $post->container_id,
            'access_token' => $accessToken,
        ]);

        $mediaId = $response->json('id');

        if (! $mediaId) {
            Log::error("Instagram recusou a publicação do post {$post->id}.", ['response' => $response->json()]);

            throw ScheduledPostException::publishFailed(Platform::INSTAGRAM->label(), $this->describeError($response));
        }

        $post->update(['payload' => [...($post->payload ?? []), 'permalink' => $this->fetchPermalink($mediaId, $accessToken)]]);
        $post->markAsPublished($mediaId);
        $this->storageService->deletePostMedia($post);

        Log::info("Post {$post->id} publicado no Instagram (mídia {$mediaId}).");
    }

    // Reaproveita o container do warmup se ainda servir; senão cria outro e acompanha até ficar pronto.
    private function processContainer(SocialAccount $account, ScheduledPost $post, bool $publish): void
    {
        $accessToken = $account->getValidToken();

        if ($post->hasValidContainer()) {
            $status = $this->containerStatus($post->container_id, $accessToken);

            if ($status === self::CONTAINER_FINISHED) {
                if ($publish) {
                    $this->publish($post, $account, $accessToken);
                }

                return;
            }

            if ($status !== null && $status !== self::CONTAINER_ERROR) {
                CheckInstagramContainerJob::dispatch($post, $account, $post->container_id, $publish);

                return;
            }

            Log::warning("Container {$post->container_id} inválido (status: {$status}). Criando outro.");
        }

        $containerId = $this->createContainer($account, $post, $accessToken);
        $post->update(['container_id' => $containerId, 'container_created_at' => now()]);

        Log::info("Container {$containerId} criado para o post {$post->id}. Acompanhando o processamento.");
        CheckInstagramContainerJob::dispatch($post, $account, $containerId, $publish);
    }

    private function createContainer(SocialAccount $account, ScheduledPost $post, string $accessToken): string
    {
        // URL temporária do S3 para a Graph API baixar a mídia.
        $mediaUrl = $this->storageService->generateDownloadUrl($post->media_path);

        $media = $this->isVideo($post->media_path)
            ? ['media_type' => 'REELS', 'video_url' => $mediaUrl]
            : ['image_url' => $mediaUrl];

        $response = Http::post($this->url("{$account->platform_id}/media"), [
            'caption' => $post->caption,
            'access_token' => $accessToken,
            ...$media,
        ]);

        $containerId = $response->json('id');

        if (! $containerId) {
            Log::error("Instagram recusou o container do post {$post->id}.", ['response' => $response->json()]);

            throw ScheduledPostException::publishFailed(Platform::INSTAGRAM->label(), $this->describeError($response));
        }

        return $containerId;
    }

    // Busca o link público do post; se falhar, a publicação vale e o post só fica sem link.
    private function fetchPermalink(string $mediaId, string $accessToken): ?string
    {
        try {
            return Http::get($this->url($mediaId), ['fields' => 'permalink', 'access_token' => $accessToken])->json('permalink');
        } catch (Throwable $e) {
            Log::warning("Não foi possível obter o link da mídia {$mediaId} do Instagram: {$e->getMessage()}");

            return null;
        }
    }

    private function describeError(Response $response): string
    {
        $reason = $response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'resposta inesperada do Instagram.';

        return Str::limit($reason, 200);
    }

    private function isVideo(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS, true);
    }

    private function url(string $endpoint): string
    {
        return self::GRAPH_API_URL."/{$endpoint}";
    }
}
