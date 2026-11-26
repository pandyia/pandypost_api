<?php

namespace App\Jobs;

use App\Enums\ScheduledPostStatus;
use App\Exceptions\BaseException;
use App\Exceptions\TikTokException;
use App\Jobs\Concerns\ChecksPlatformPostStatus;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
use App\Services\TikTok\TikTokApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Confere o resultado do Direct Post e é o único a marcar `published` (ou `failed`) depois do
 * upload. O vídeo só sai do S3 em caso de sucesso; em falha fica para reenvio (spec 012).
 */
class CheckTikTokPostStatusJob implements ShouldQueue
{
    use ChecksPlatformPostStatus, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ScheduledPost $post,
        public SocialAccount $account,
        public string $publishId
    ) {
        $this->onQueue('tiktok');
    }

    public function handle(StorageService $storageService, TikTokApi $api): void
    {
        try {
            $data = $api->fetchStatus($this->account->getValidToken(), $this->publishId);
        } catch (TikTokException $e) {
            // Token/permissão: repetir 15x não resolve. Instabilidade do TikTok segue para o retry normal.
            if (! $e->isPermanent()) {
                throw $e;
            }

            $this->markFailed($e->getMessage());

            return;
        }

        $status = $data['status'] ?? null;

        Log::info("TikTok Publish ID {$this->publishId} - Status: {$status} (tentativa {$this->attempts()}/{$this->tries})");

        match ($status) {
            'PUBLISH_COMPLETE' => $this->handleSuccess($data, $storageService),
            'FAILED' => $this->markFailed(TikTokException::fromApiCode($data['fail_reason'] ?? null)->getMessage()),
            default => $this->retryWithBackoff(),
        };
    }

    protected function timeoutException(): BaseException
    {
        return TikTokException::publishTimeout();
    }

    protected function stillProcessingLogMessage(int $delay): string
    {
        return "TikTok post ainda processando. Próxima tentativa em {$delay}s";
    }

    private function handleSuccess(array $data, StorageService $storageService): void
    {
        // (sic) o TikTok escreve "publicaly" com um L só. Em sandbox/não auditado não há ID público.
        $videoId = $data['publicaly_available_post_id'][0] ?? null;

        $this->post->update([
            'status' => ScheduledPostStatus::PUBLISHED->value,
            'platform_post_id' => $videoId ? (string) $videoId : null,
            'published_at' => now(),
            'error_message' => null,
        ]);

        Log::info("Post {$this->post->id} publicado com sucesso no TikTok!");

        $storageService->deleteIfUnused($this->post->mediaPaths(), $this->post->id);
    }

    protected function markFailed(string $message): void
    {
        // Estado final nunca é sobrescrito: o post pode ter sido publicado ou cancelado enquanto o job rodava.
        $current = ScheduledPost::withoutGlobalScopes()->whereKey($this->post->getKey())->value('status');

        if (in_array($current, [ScheduledPostStatus::PUBLISHED->value, ScheduledPostStatus::CANCELLED->value], true)) {
            Log::warning("Post TikTok {$this->post->id} já está {$current}. Falha ignorada.");

            return;
        }

        $this->post->update([
            'status' => ScheduledPostStatus::FAILED->value,
            'error_message' => mb_substr($message, 0, 255),
        ]);

        Log::error("Falha no post TikTok {$this->post->id}: {$message}");
    }
}
