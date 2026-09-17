<?php

namespace App\Jobs;

use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTikTokPostStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 15;

    public function __construct(
        public ScheduledPost $post,
        public SocialAccount $account,
        public string $publishId
    ) {
        $this->onQueue('tiktok');
    }

    public function backoff(): array
    {
        return [2, 4, 8, 16, 30];
    }

    public function handle(StorageService $storageService): void
    {
        $accessToken = $this->account->getValidToken();
        $attempt = $this->attempts();

        $response = Http::withToken($accessToken)
            ->post('https://open.tiktokapis.com/v2/post/publish/status/fetch/', [
                'publish_id' => $this->publishId,
            ])->json();

        $data = $response['data'] ?? [];
        $status = $data['status'] ?? null;
        $failReason = $data['fail_reason'] ?? null;
        $publicityId = $data['publicity_id'] ?? $this->publishId;

        Log::info("TikTok Publish ID {$this->publishId} - Status: {$status} (tentativa {$attempt}/{$this->tries})");

        match ($status) {
            'PUBLISH_COMPLETE' => $this->handleSuccess($publicityId, $storageService),
            'FAILED'           => $this->handleFailed($failReason, $storageService),
            default            => $this->retryWithBackoff(),
        };
    }

    private function retryWithBackoff(): void
    {
        $backoffValues = $this->backoff();
        $attempt = min($this->attempts() - 1, count($backoffValues) - 1);
        $delay = $backoffValues[$attempt] ?? 30;

        Log::info("TikTok post ainda processando. Próxima tentativa em {$delay}s");
        $this->release($delay);
    }

    private function handleSuccess(string $platformPostId, StorageService $storageService): void
    {
        $this->post->update([
            'status' => 'published',
            'platform_post_id' => $platformPostId,
            'published_at' => now(),
        ]);

        Log::info("Post {$this->post->id} publicado com sucesso no TikTok!");
        $storageService->deleteIfUnused([$this->post->media_path], $this->post->id);
    }

    private function handleFailed(?string $failReason, StorageService $storageService): void
    {
        $error = "Erro no TikTok: " . ($failReason ?: 'Falha na publicação');

        $this->post->update([
            'status' => 'failed',
            'error_message' => substr($error, 0, 255),
        ]);

        Log::error("Falha no post TikTok {$this->post->id}: {$error}");
        $storageService->deleteIfUnused([$this->post->media_path], $this->post->id);
        $this->fail(new Exception($error));
    }
}
