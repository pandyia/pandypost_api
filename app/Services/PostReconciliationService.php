<?php

namespace App\Services;

use App\Enums\Exceptions\ScheduledPostError;
use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Exceptions\InstagramException;
use App\Exceptions\ScheduledPostException;
use App\Exceptions\TikTokException;
use App\Models\ScheduledPost;
use App\Services\Instagram\InstagramApi;
use App\Services\Storage\StorageService;
use App\Services\TikTok\TikTokApi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rede de segurança da esteira (spec 020): resolve posts parados em `processing` consultando a rede antes de
 * decidir. `published` só quando a rede confirma; `failed` só quando a rede não tem o vídeo (ou não dá para saber).
 */
class PostReconciliationService
{
    /** Minutos em `processing` até o post ser conferido. */
    private const LIMITS = ['tiktok' => 30, 'youtube' => 30, 'instagram' => 60];

    /** Janela (horas) em que um post fechado pelo comando ainda pode ser promovido se a rede confirmar. */
    private const LATE_PROMOTION_HOURS = 6;

    private const PUBLISHED = 'published';

    private const FAILED = 'failed';

    private const WAITING = 'waiting';

    private const UNKNOWN = 'unknown';

    /** A consulta à rede falhou (fora do ar, token revogado...): não sabemos o estado. */
    private const ERROR = 'error';

    public function __construct(
        private readonly TikTokApi $tiktok,
        private readonly InstagramApi $instagram,
        private readonly StorageService $storage,
    ) {}

    /**
     * Confere os posts parados e os recém-fechados pelo comando.
     *
     * @return array{published: int, failed: int, waiting: int}
     */
    public function run(): array
    {
        $result = ['published' => 0, 'failed' => 0, 'waiting' => 0];

        foreach ($this->recentlyClosedPosts() as $post) {
            if ($this->promoteIfPublished($post)) {
                $result['published']++;
            }
        }

        // Depois: um post recém-fechado nesta rodada não é reconferido na mesma execução.
        foreach ($this->stuckPosts() as $post) {
            $result[$this->reconcileStuck($post)]++;
        }

        return $result;
    }

    /**
     * Antes de reenviar: se a rede já tem o vídeo, o post vira `published` e o reenvio é recusado.
     */
    public function guardRetry(ScheduledPost $post): void
    {
        if (! $post->container_id && ! $post->platform_post_id) {
            return;
        }

        [$state] = $this->verify($post);

        if ($state === self::PUBLISHED) {
            $this->close($post, ScheduledPostStatus::PUBLISHED->value, null, ScheduledPostStatus::FAILED->value);

            throw ScheduledPostException::alreadyPublished();
        }

        // A rede respondeu que ainda processa: reenviar agora publicaria em duplicidade. Erro de consulta não bloqueia
        // (token revogado, rede fora do ar): o usuário precisa poder reenviar depois de reconectar.
        if ($state === self::WAITING) {
            throw ScheduledPostException::stillProcessing();
        }
    }

    /** @return Collection<int, ScheduledPost> */
    private function stuckPosts(): Collection
    {
        return ScheduledPost::withoutGlobalScopes()
            ->where('status', ScheduledPostStatus::PROCESSING->value)
            ->where(function ($query) {
                foreach (self::LIMITS as $platform => $minutes) {
                    $query->orWhere(fn ($q) => $q->where('platform', $platform)->where('updated_at', '<', now()->subMinutes($minutes)));
                }
            })
            ->with('socialAccount')
            ->get();
    }

    /** @return Collection<int, ScheduledPost> */
    private function recentlyClosedPosts(): Collection
    {
        return ScheduledPost::withoutGlobalScopes()
            ->where('status', ScheduledPostStatus::FAILED->value)
            ->where('failed_at', '>', now()->subHours(self::LATE_PROMOTION_HOURS))
            ->whereIn('error_message', [ScheduledPostError::STUCK_TIMEOUT->message(), ScheduledPostError::UNVERIFIABLE->message()])
            ->where(fn ($q) => $q->whereNotNull('container_id')->orWhereNotNull('platform_post_id'))
            ->with('socialAccount')
            ->get();
    }

    /** @return 'published'|'failed'|'waiting' */
    private function reconcileStuck(ScheduledPost $post): string
    {
        [$state, $message] = $this->verify($post);

        if ($state === self::PUBLISHED) {
            return $this->close($post, ScheduledPostStatus::PUBLISHED->value) ? 'published' : 'waiting';
        }

        if ($state === self::WAITING || $state === self::ERROR) {
            // Rede ainda processando (ou fora do ar): espera, mas não para sempre.
            if ($post->updated_at->gt(now()->subMinutes(self::LIMITS[$post->platform->value] * 2))) {
                return 'waiting';
            }

            $message = ScheduledPostError::STUCK_TIMEOUT->message();
        }

        $message ??= ScheduledPostError::UNVERIFIABLE->message();

        return $this->close($post, ScheduledPostStatus::FAILED->value, $message) ? 'failed' : 'waiting';
    }

    private function promoteIfPublished(ScheduledPost $post): bool
    {
        if ($post->status !== ScheduledPostStatus::FAILED->value) {
            return false;
        }

        if (! $post->container_id && ! $post->platform_post_id) {
            return false;
        }

        [$state] = $this->verify($post);

        return $state === self::PUBLISHED && $this->close($post, ScheduledPostStatus::PUBLISHED->value, null, ScheduledPostStatus::FAILED->value);
    }

    /**
     * Pergunta à rede. Devolve [estado, mensagem de falha em pt-BR da rede, quando houver].
     *
     * @return array{0: string, 1: ?string}
     */
    private function verify(ScheduledPost $post): array
    {
        try {
            return match ($post->platform) {
                Platform::YOUTUBE => [$post->platform_post_id ? self::PUBLISHED : self::UNKNOWN, null],
                Platform::TIKTOK => $this->verifyTikTok($post),
                Platform::INSTAGRAM => $this->verifyInstagram($post),
                default => [self::UNKNOWN, null],
            };
        } catch (Throwable $e) {
            // Sem getMessage(): a de terceiros pode trazer token ou URL.
            Log::warning("Post {$post->id}: consulta à rede falhou (".$e::class.').');

            return [self::ERROR, null];
        }
    }

    /** @return array{0: string, 1: ?string} */
    private function verifyTikTok(ScheduledPost $post): array
    {
        if (! $post->container_id || ! $post->socialAccount) {
            return [self::UNKNOWN, null];
        }

        $data = $this->tiktok->fetchStatus($post->socialAccount->getValidToken(), $post->container_id);

        return match ($data['status'] ?? null) {
            'PUBLISH_COMPLETE' => [self::PUBLISHED, null],
            'FAILED' => [self::FAILED, TikTokException::fromApiCode($data['fail_reason'] ?? null)->getMessage()],
            default => [self::WAITING, null],
        };
    }

    /** @return array{0: string, 1: ?string} */
    private function verifyInstagram(ScheduledPost $post): array
    {
        if (! $post->container_id || ! $post->socialAccount) {
            return [self::UNKNOWN, null];
        }

        return match ($this->instagram->containerStatus($post->socialAccount->getValidToken(), $post->container_id)) {
            'PUBLISHED' => [self::PUBLISHED, null],
            'ERROR', 'EXPIRED' => [self::FAILED, InstagramException::containerFailed()->getMessage()],
            // Container pronto que ninguém publicou: nada foi ao ar; o reenvio cria outro.
            'FINISHED' => [self::FAILED, ScheduledPostError::STUCK_TIMEOUT->message()],
            'IN_PROGRESS' => [self::WAITING, null],
            default => [self::UNKNOWN, null],
        };
    }

    /**
     * Troca o estado só se o post ainda está em `$from` (linha travada): um job que terminou no mesmo
     * instante não é desfeito. O update() do model mantém `failed_at` e as notificações.
     */
    private function close(ScheduledPost $post, string $to, ?string $message = null, string $from = 'processing'): bool
    {
        $closed = DB::transaction(function () use ($post, $to, $message, $from) {
            $locked = ScheduledPost::withoutGlobalScopes()->whereKey($post->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->status !== $from) {
                return false;
            }

            $locked->update($to === ScheduledPostStatus::PUBLISHED->value
                ? ['status' => $to, 'published_at' => now(), 'error_message' => null]
                : ['status' => $to, 'error_message' => mb_substr((string) $message, 0, 255)]);

            return true;
        });

        if (! $closed) {
            return false;
        }

        Log::warning("Post {$post->id} ({$post->platform->value}) conferido pelo comando: {$to}.");

        if ($to === ScheduledPostStatus::PUBLISHED->value) {
            $this->storage->deleteIfUnused($post->mediaPaths(), $post->id);
        }

        return true;
    }
}
