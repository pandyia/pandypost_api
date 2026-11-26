<?php

namespace App\Jobs;

use App\Enums\Exceptions\ScheduledPostError;
use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Exceptions\BaseException;
use App\Exceptions\InstagramException;
use App\Exceptions\ScheduledPostException;
use App\Exceptions\TikTokException;
use App\Exceptions\YouTubeException;
use App\Models\ScheduledPost;
use App\Services\Factories\SocialMediaFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public ScheduledPost $post)
    {
        $this->onQueue($post->platform->value); // separando filas por plataforma

        // Alinhado ao supervisor da rede (config/horizon.php) e abaixo do retry_after do Redis (360 s):
        // upload de vídeo cortado no meio é repetido e pode duplicar o vídeo.
        $this->timeout = $post->platform === Platform::INSTAGRAM ? 180 : 300;
    }

    public function handle(SocialMediaFactory $factory): void
    {
        // Post cancelado, já publicado ou já falho não é publicado de novo (job atrasado, retry depois do sucesso).
        if (! $this->isStillPublishable()) {
            Log::info("Post {$this->post->id} não está mais pendente. Publicação ignorada.");

            return;
        }

        try {
            $this->publish($factory);
        } catch (BaseException $e) {
            // Erro permanente (reconectar conta, cota, privacidade...): repetir só gasta cota da rede.
            if (! $this->job || ! $this->isPermanent($e)) {
                throw $e;
            }

            // fail() chama failed() (grava o post) e registra um único failed_jobs; o job não volta para a fila.
            $this->fail($e);
        }
    }

    private function publish(SocialMediaFactory $factory): void
    {
        // Verificação Tardinha: Se o usuário cancelou a assinatura entre a data do clique e a data do post, nós barramos.
        $user = $this->post->user;
        if (! $user->hasValidSubscriptionForPublishing()) {
            throw ScheduledPostException::subscriptionInactive();
        }

        $account = $this->post->socialAccount;

        // A conta pertence ao workspace (spec 017). ScheduledPost não tem workspace_id: vale o da conta, e o
        // autor precisa ter acesso a ele. Não usa o currentAccess: o autor pode ter trocado de workspace depois de agendar.
        if (! $account || ! $user->accesses()->where('workspace_id', $account->workspace_id)->exists()) {
            throw ScheduledPostException::accountNotFound();
        }

        Log::info("Publicando post {$this->post->id} em {$this->post->platform->value} [queue: {$this->post->platform->value}]");

        $service = $factory->make($this->post->platform);
        $service->upload($account, $this->post);
    }

    private function isStillPublishable(): bool
    {
        // Consulta direta: refresh() recarregaria as relações já carregadas e o worker não tem workspace corrente.
        $status = ScheduledPost::withoutGlobalScopes()->whereKey($this->post->getKey())->value('status');

        return in_array($status, [ScheduledPostStatus::PENDING->value, ScheduledPostStatus::PROCESSING->value], true);
    }

    private function isPermanent(BaseException $e): bool
    {
        return match (true) {
            $e instanceof ScheduledPostException => in_array($e->getErrorCode(), [
                ScheduledPostError::ACCOUNT_NOT_FOUND->value,
                ScheduledPostError::SUBSCRIPTION_INACTIVE->value,
            ], true),
            $e instanceof YouTubeException,
            $e instanceof InstagramException,
            $e instanceof TikTokException => $e->isPermanent(),
            default => false,
        };
    }

    public function failed(Throwable $exception): void
    {
        // Nunca sobrescreve um estado final: o post pode ter sido publicado ou cancelado enquanto o job tentava.
        if (! $this->isStillPublishable()) {
            Log::warning("Post {$this->post->id} já saiu de pending/processing. Falha do job ignorada.");

            return;
        }

        // Sem getMessage(): a de terceiros pode trazer URL assinada ou token. Só classe e código de domínio.
        $code = $exception instanceof BaseException ? $exception->getErrorCode() : 'n/a';
        Log::error("Falha no post {$this->post->id} (".$exception::class.", código {$code}).");

        // Só exceção de domínio (mensagem nossa, pt-BR) chega ao usuário; a de terceiros pode trazer URL assinada.
        $message = $exception instanceof BaseException
            ? $exception->getMessage()
            : 'Não foi possível publicar o post. Tente novamente em instantes.';

        $this->post->update([
            'status' => ScheduledPostStatus::FAILED->value,
            'error_message' => substr($message, 0, 255),
        ]);

        // A mídia fica guardada para reenvio (limpeza em posts:purge-failed-media) e o aviso
        // de falha sai do observer do ScheduledPost.
    }
}
