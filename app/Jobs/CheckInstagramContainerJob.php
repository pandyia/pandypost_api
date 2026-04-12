<?php

namespace App\Jobs;

use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CheckInstagramContainerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const GRAPH_API_VERSION = 'v24.0';
    private const GRAPH_API_BASE_URL = 'https://graph.instagram.com';

    public int $tries = 15;

    public function __construct(
        public ScheduledPost $post,
        public SocialAccount $account,
        public string $containerId,
        public bool $shouldPublish = true
    ) {
        $this->onQueue('instagram');
    }

    public function backoff(): array
    {
        return [2, 4, 8, 16, 30];
    }

    public function handle(): void
    {
        $accessToken = $this->account->getValidToken();
        $status = $this->getContainerStatus($accessToken);
        $attempt = $this->attempts();

        Log::info("Container {$this->containerId} - Status: {$status} (tentativa {$attempt}/{$this->tries}) [Publish: " . ($this->shouldPublish ? 'Sim' : 'Não') . "]");

        match ($status) {
            'FINISHED' => $this->handleFinishedContainer($accessToken),
            'ERROR' => $this->handleError(),
            default => $this->retryWithBackoff(),
        };
    }

    private function retryWithBackoff(): void
    {
        $backoffValues = $this->backoff();
        $attempt = min($this->attempts() - 1, count($backoffValues) - 1);
        $delay = $backoffValues[$attempt] ?? 30;
        
        Log::info("Container ainda processando. Próxima tentativa em {$delay}s");
        $this->release($delay);
    }

    private function getContainerStatus(string $accessToken): ?string
    {
        $response = Http::get(
            self::GRAPH_API_BASE_URL . '/' . self::GRAPH_API_VERSION . '/' . $this->containerId,
            [
                'fields' => 'status_code',
                'access_token' => $accessToken,
            ]
        )->json();

        return $response['status_code'] ?? null;
    }

    private function handleFinishedContainer(string $accessToken): void
    {
        if (!$this->shouldPublish) {
            Log::info("Container {$this->containerId} pronto para uso futuro! (Warmup completo)");
            return;
        }

        $this->publishContainer($accessToken);
    }

    private function publishContainer(string $accessToken): void
    {
        $response = Http::post(
            self::GRAPH_API_BASE_URL . '/' . self::GRAPH_API_VERSION . '/' . $this->account->platform_id . '/media_publish',
            [
                'creation_id' => $this->containerId,
                'access_token' => $accessToken,
            ]
        )->json();

        if (isset($response['error'])) {
            Log::error('Falha ao publicar no Instagram', ['response' => $response]);
            $this->markAsFailed('Erro na publicação: ' . json_encode($response));
            return;
        }

        $this->markAsPublished($response['id'] ?? null);
    }

    private function markAsPublished(?string $platformPostId): void
    {
        $this->post->update([
            'status' => 'published',
            'platform_post_id' => $platformPostId,
            'published_at' => now(),
        ]);

        $this->post->user->subscription?->increment('posts_used');

        Log::info("Post {$this->post->id} publicado com sucesso no Instagram!");
    }

    private function markAsFailed(string $error): void
    {
        $this->post->update([
            'status' => 'failed',
            'error_message' => substr($error, 0, 255),
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
        
        $this->markAsFailed('Erro no processamento do container pelo Instagram');
        $this->fail(new Exception('Container processing failed'));
    }
}

