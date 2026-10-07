<?php

namespace App\Jobs;

use App\Enums\Platform;
use App\Exceptions\ScheduledPostException;
use App\Jobs\Concerns\PollsPlatformStatus;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\InstagramService;
use App\Services\Storage\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// Acompanha o container do Instagram e publica quando fica pronto; no warmup ($shouldPublish = false) só espera.
class CheckInstagramContainerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PollsPlatformStatus, Queueable, SerializesModels;

    private const MONITORING_WINDOW_MINUTES = 30;

    private const POLL_DELAYS_SECONDS = [5, 10, 20, 30, 60];

    // Erros na consulta ou na publicação (rede, token, limite) tolerados antes de desistir.
    public int $maxExceptions = 3;

    public function __construct(
        public ScheduledPost $post,
        public SocialAccount $account,
        public string $containerId,
        public bool $shouldPublish = true
    ) {
        $this->onQueue(Platform::INSTAGRAM->value);
    }

    // Pronto publica, erro descarta; qualquer outro status volta para a fila e consulta de novo.
    public function handle(InstagramService $instagram): void
    {
        $accessToken = $this->account->getValidToken();
        $status = $instagram->containerStatus($this->containerId, $accessToken);

        Log::info("Container {$this->containerId} - Status: {$status} (tentativa {$this->attempts()}) [publicar: ".($this->shouldPublish ? 'sim' : 'não').']');

        match ($status) {
            InstagramService::CONTAINER_FINISHED => $this->onContainerReady($instagram, $accessToken),
            InstagramService::CONTAINER_ERROR => $this->onContainerError(),
            default => $this->release($this->nextPollDelay()),
        };
    }

    // Prazo esgotado ou erros demais: marca o post como falho (no warmup só registra no log).
    public function failed(Throwable $exception): void
    {
        if (! $this->shouldPublish) {
            Log::warning("[Warmup] Container {$this->containerId} não ficou pronto: {$exception->getMessage()}");

            return;
        }

        $this->post->markAsFailed($exception instanceof MaxAttemptsExceededException
            ? 'O Instagram não terminou de processar a mídia dentro do prazo de acompanhamento.'
            : $exception->getMessage());

        app(StorageService::class)->deletePostMedia($this->post);
    }

    private function onContainerReady(InstagramService $instagram, string $accessToken): void
    {
        if (! $this->shouldPublish) {
            Log::info("[Warmup] Container {$this->containerId} pronto para a publicação.");

            return;
        }

        $instagram->publish($this->post, $this->account, $accessToken);
    }

    // Descarta o container com erro; no warmup a publicação cria outro, na publicação o post falha.
    private function onContainerError(): void
    {
        $this->post->update(['container_id' => null, 'container_created_at' => null]);

        if ($this->shouldPublish) {
            $this->fail(ScheduledPostException::publishFailed(Platform::INSTAGRAM->label(), 'o Instagram não conseguiu processar a mídia.'));
        }
    }
}
