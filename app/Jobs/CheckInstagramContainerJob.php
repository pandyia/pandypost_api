<?php

namespace App\Jobs;

use App\Enums\ScheduledPostStatus;
use App\Exceptions\BaseException;
use App\Exceptions\InstagramException;
use App\Jobs\Concerns\ChecksPlatformPostStatus;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Instagram\InstagramApi;
use App\Services\InstagramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckInstagramContainerJob implements ShouldQueue
{
    use ChecksPlatformPostStatus, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ScheduledPost $post,
        public SocialAccount $account,
        public string $containerId,
        public bool $shouldPublish = true
    ) {
        $this->onQueue('instagram');
    }

    public function handle(): void
    {
        // Post cancelado, já publicado ou já falho: job antigo (warmup, retry depois do sucesso) não age.
        if (! $this->isStillPublishable()) {
            Log::info("Post {$this->post->id} não está mais pendente. Verificação do container {$this->containerId} ignorada.");

            return;
        }

        $accessToken = $this->account->getValidToken();

        try {
            $status = app(InstagramApi::class)->containerStatus($accessToken, $this->containerId);
        } catch (InstagramException $e) {
            // Token/permissão: repetir 15x não resolve. Instabilidade da Meta segue para o retry normal.
            if (! $e->isPermanent()) {
                throw $e;
            }

            $this->failUnlessWarmup($e->getMessage());

            return;
        }

        $attempt = $this->attempts();

        Log::info("Container {$this->containerId} - Status: {$status} (tentativa {$attempt}/{$this->tries}) [Publish: ".($this->shouldPublish ? 'Sim' : 'Não').']');

        match ($status) {
            'FINISHED' => $this->handleFinishedContainer($accessToken),
            'PUBLISHED' => app(InstagramService::class)->markAlreadyPublished($this->post),
            'ERROR', 'EXPIRED' => $this->handleError(),
            default => $this->retryWithBackoff(),
        };
    }

    protected function timeoutException(): BaseException
    {
        return InstagramException::publishTimeout();
    }

    protected function stillProcessingLogMessage(int $delay): string
    {
        return "Container ainda processando. Próxima tentativa em {$delay}s";
    }

    private function handleFinishedContainer(string $accessToken): void
    {
        if (! $this->shouldPublish) {
            Log::info("Container {$this->containerId} pronto para uso futuro! (Warmup completo)");

            return;
        }

        // O container deste job, não o $post->container_id (que pode ter sido recriado depois).
        app(InstagramService::class)->publishContainer($this->post, $this->account, $accessToken, $this->containerId);
    }

    private function isStillPublishable(): bool
    {
        $status = ScheduledPost::withoutGlobalScopes()->whereKey($this->post->getKey())->value('status');

        return in_array($status, [ScheduledPostStatus::PENDING->value, ScheduledPostStatus::PROCESSING->value], true);
    }

    /** Warmup é melhor-esforço: o erro dele não derruba o post, a publicação na hora cria o container de novo. */
    private function failUnlessWarmup(string $error): void
    {
        if ($this->shouldPublish) {
            $this->markFailed($error);

            return;
        }

        Log::warning("Warmup do post {$this->post->id} falhou ({$error}). O post segue pendente.");
    }

    protected function markFailed(string $error): void
    {
        $this->post->update([
            'status' => ScheduledPostStatus::FAILED->value,
            'error_message' => mb_substr($error, 0, 255),
        ]);

        Log::error("Falha no post {$this->post->id}: {$error}");
    }

    private function handleError(): void
    {
        // Limpa container inválido para permitir nova tentativa
        $this->post->update([
            'container_id' => null,
            'container_created_at' => null,
        ]);

        $this->failUnlessWarmup(InstagramException::containerFailed()->getMessage());
    }
}
