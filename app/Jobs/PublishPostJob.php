<?php

namespace App\Jobs;

use App\Exceptions\ScheduledPostException;
use App\Exceptions\SubscriptionException;
use App\Models\ScheduledPost;
use App\Services\Factories\SocialMediaFactory;
use App\Services\Storage\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// Publica um post na fila da plataforma. Sem $timeout próprio: vale o do supervisor (config/horizon.php).
class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // Espera entre as tentativas, para erros temporários (rede, limite de requisições) terem chance de passar.
    public array $backoff = [30, 120];

    public function __construct(public ScheduledPost $post)
    {
        $this->onQueue($post->platform->value);
    }

    // Confere assinatura e conta, e passa o upload para o service da plataforma.
    public function handle(SocialMediaFactory $factory): void
    {
        // A assinatura pode ter vencido entre o agendamento e a publicação.
        if (! $this->post->user->hasValidSubscriptionForPublishing()) {
            throw SubscriptionException::subscriptionInactive();
        }

        $account = $this->post->socialAccount;

        if (! $account || $account->user_id !== $this->post->user_id) {
            throw ScheduledPostException::noAccountLinked($this->post->platform->label());
        }

        Log::info("Publicando post {$this->post->id} em {$this->post->platform->value}.");

        $factory->make($this->post->platform)->upload($account, $this->post);
    }

    // Depois da última tentativa: marca o post como falho e apaga a mídia.
    public function failed(Throwable $exception): void
    {
        Log::error("Falha definitiva no post {$this->post->id}: {$exception->getMessage()}");

        $this->post->markAsFailed($exception->getMessage());
        app(StorageService::class)->deletePostMedia($this->post);
    }
}
