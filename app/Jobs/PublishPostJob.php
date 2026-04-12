<?php

namespace App\Jobs;

use App\Models\ScheduledPost;
use App\Services\Factories\SocialMediaFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(public ScheduledPost $post)
    {
        $this->onQueue($post->platform->value); // separando filas por plataforma
    }

    public function handle(SocialMediaFactory $factory): void
    {
        // Verificação Tardinha: Se o usuário cancelou a assinatura entre a data do clique e a data do post, nós barramos.
        $user = $this->post->user;
        if (!$user->hasValidSubscriptionForPublishing()) {
            throw new \Exception("Assinatura Inativa ou Expirada no momento exato do agendamento.");
        }

        $account = $this->post->socialAccount;

        if (!$account || $account->user_id !== $user->id) {
            throw new \Exception("Conta vinculada ao agendamento não encontrada.");
        }

        Log::info("Publicando post {$this->post->id} em {$this->post->platform->value} [queue: {$this->post->platform->value}]");

        $service = $factory->make($this->post->platform);
        $service->upload($account, $this->post);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Falha no post {$this->post->id}: {$exception->getMessage()}");

        $this->post->update([
            'status' => 'failed',
            'error_message' => substr($exception->getMessage(), 0, 255),
        ]);
        
        // Limpa a sujeira do S3/Local se o Post falhou de vez e caiu no Failed Jobs
        if (Storage::exists($this->post->media_path)) {
            Storage::delete($this->post->media_path);
        }

        $payload = $this->post->payload ?? [];
        if (!empty($payload['thumbnail_path']) && Storage::exists($payload['thumbnail_path'])) {
            Storage::delete($payload['thumbnail_path']);
        }
    }
}
