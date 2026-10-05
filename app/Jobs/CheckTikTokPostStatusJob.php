<?php

namespace App\Jobs;

use App\Enums\Platform;
use App\Exceptions\ScheduledPostException;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta o status de uma publicação no TikTok até ela ser concluída ou recusada.
 * O TikTok processa e modera o vídeo depois do upload; isso costuma levar ~1 min, mas pode demorar mais.
 */
class CheckTikTokPostStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const STATUS_URL = 'https://open.tiktokapis.com/v2/post/publish/status/fetch/';

    private const MONITORING_WINDOW_MINUTES = 60;

    private const POLL_DELAYS_SECONDS = [5, 10, 20, 30, 60];

    // Erros na consulta (rede, token) tolerados antes de desistir.
    public int $maxExceptions = 3;

    public function __construct(
        public ScheduledPost $post,
        public SocialAccount $account,
        public string $publishId
    ) {
        $this->onQueue(Platform::TIKTOK->value);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(self::MONITORING_WINDOW_MINUTES);
    }

    public function backoff(): array
    {
        return self::POLL_DELAYS_SECONDS;
    }

    public function handle(StorageService $storageService): void
    {
        $data = $this->fetchStatus();
        $status = $data['status'] ?? null;

        Log::info("TikTok publish_id {$this->publishId} - Status: {$status} (tentativa {$this->attempts()})");

        match ($status) {
            'PUBLISH_COMPLETE' => $this->markAsPublished($data, $storageService),
            'FAILED' => $this->markAsFailed('Erro no TikTok: '.($data['fail_reason'] ?? 'falha na publicação'), $storageService),
            default => $this->release($this->nextPollDelay()),
        };
    }

    /**
     * Prazo de acompanhamento esgotado ou erros repetidos na consulta.
     */
    public function failed(Throwable $exception): void
    {
        $message = $exception instanceof MaxAttemptsExceededException
            ? 'O TikTok não confirmou a publicação dentro do prazo de acompanhamento.'
            : $exception->getMessage();

        $this->markAsFailed($message, app(StorageService::class));
    }

    private function fetchStatus(): array
    {
        $response = Http::withToken($this->account->getValidToken())
            ->post(self::STATUS_URL, ['publish_id' => $this->publishId]);

        $errorCode = $response->json('error.code');

        if ($errorCode && $errorCode !== 'ok') {
            throw ScheduledPostException::publishFailed(
                Platform::TIKTOK->label(),
                "não foi possível consultar o status da publicação ({$errorCode}).",
            );
        }

        return $response->json('data') ?? [];
    }

    private function nextPollDelay(): int
    {
        $index = min($this->attempts() - 1, count(self::POLL_DELAYS_SECONDS) - 1);

        return self::POLL_DELAYS_SECONDS[$index];
    }

    /**
     * O id público só existe quando o vídeo é público e já passou pela moderação;
     * posts privados (ou ainda em moderação) ficam sem id.
     */
    private function markAsPublished(array $data, StorageService $storageService): void
    {
        $publicPostId = Arr::first($data['publicaly_available_post_id'] ?? []);

        $this->post->update([
            'status' => 'published',
            'platform_post_id' => $publicPostId !== null ? (string) $publicPostId : null,
            'published_at' => now(),
        ]);

        Log::info("Post {$this->post->id} publicado com sucesso no TikTok!");
        $this->cleanupFiles($storageService);
    }

    private function markAsFailed(string $error, StorageService $storageService): void
    {
        $this->post->update([
            'status' => 'failed',
            'error_message' => mb_substr($error, 0, 255),
        ]);

        Log::error("Falha no post TikTok {$this->post->id}: {$error}");
        $this->cleanupFiles($storageService);
    }

    private function cleanupFiles(StorageService $storageService): void
    {
        $paths = [$this->post->media_path, Arr::get($this->post->payload ?? [], 'thumbnail_path')];

        $storageService->deleteIfUnused($paths, $this->post->id);
    }
}
