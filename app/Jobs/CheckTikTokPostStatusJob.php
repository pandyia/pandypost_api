<?php

namespace App\Jobs;

use App\Enums\Platform;
use App\Exceptions\ScheduledPostException;
use App\Jobs\Concerns\PollsPlatformStatus;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
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

// Consulta o status do post no TikTok até publicar ou falhar (processar e moderar leva ~1 min).
class CheckTikTokPostStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PollsPlatformStatus, Queueable, SerializesModels;

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

    // Publicado ou recusado encerra; qualquer outro status volta para a fila e consulta de novo.
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

    // Prazo esgotado ou erros demais na consulta: marca o post como falho.
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

    // O id público só vem para vídeo público já moderado; senão o post fica sem link.
    private function markAsPublished(array $data, StorageService $storageService): void
    {
        $publicPostId = Arr::first($data['publicaly_available_post_id'] ?? []);

        $this->post->markAsPublished($publicPostId !== null ? (string) $publicPostId : null);
        $storageService->deletePostMedia($this->post);

        Log::info("Post {$this->post->id} publicado com sucesso no TikTok!");
    }

    private function markAsFailed(string $error, StorageService $storageService): void
    {
        $this->post->markAsFailed($error);
        $storageService->deletePostMedia($this->post);

        Log::error("Falha no post TikTok {$this->post->id}: {$error}");
    }
}
