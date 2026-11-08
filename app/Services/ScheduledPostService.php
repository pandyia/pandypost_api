<?php

namespace App\Services;

use App\Enums\Exceptions\ScheduledPostError;
use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Exceptions\ScheduledPostException;
use App\Jobs\PublishPostJob;
use App\Models\ContentPipeline;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Billing\Tenant\SubscriptionService;
use App\Services\Factories\PayloadBuilderFactory;
use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduledPostService extends BaseService
{
    protected array $with = ['socialAccount'];

    protected array $normalFilter = ['status'];

    public function __construct(
        ScheduledPost $scheduledPost,
        private readonly PayloadBuilderFactory $payloadBuilderFactory,
        private readonly ContentPipelineService $pipelineService,
        private readonly SubscriptionService $subscriptionService,
        private readonly StorageService $storageService,
    ) {
        parent::__construct($scheduledPost);
    }

    /**
     * Agenda posts para uma ou mais contas sociais.
     *
     * O vídeo e a thumbnail já estão no S3 — recebemos apenas os paths
     * validados pelo StoragePathRule no FormRequest.
     */
    public function schedule(User $user, array $data): Collection
    {
        $this->subscriptionService->ensureValidSubscription($user);

        $data['scheduled_at'] = $this->toAppTimezone($data['scheduled_at'] ?? null);
        $accountUuids = $data['social_account_uuids'];
        $pipelineCardUuid = Arr::pull($data, 'pipeline_card_uuid');
        $mediaStoragePath = $data['media_storage_path'];
        // Reserva no clique: o vídeo ainda está subindo, o job só é despachado em completeUpload().
        $awaitingUpload = (bool) Arr::pull($data, 'media_pending', false);

        return DB::transaction(function () use ($user, $accountUuids, $pipelineCardUuid, $data, $mediaStoragePath, $awaitingUpload) {
            $this->subscriptionService->consumeQuota($user, count($accountUuids));

            // Todas as contas são conferidas antes de criar qualquer post: tudo ou nada, sem job despachado à toa.
            $accounts = collect($accountUuids)->map(fn (string $uuid) => $this->ensureValidSocialAccount($user, $uuid));

            $posts = $accounts->map(fn (SocialAccount $account) => $this->createPostForAccount($user, $account, $mediaStoragePath, $data, $awaitingUpload));

            $this->handlePipelineCard($pipelineCardUuid, $posts); // TODO tem a ver com kanban, não implementado ainda no frontend.

            return $posts;
        });
    }

    /**
     * Cancela um post agendado.
     * Só é possível cancelar posts com status "pending" ou "awaiting_upload". Qualquer membro com permissão no workspace pode cancelar
     * (a conta é do workspace); o escopo de tenant isola os demais workspaces (404).
     */
    public function cancel(string $uuid): void
    {
        $post = ScheduledPost::where('uuid', $uuid)->firstOrFail();

        if (! in_array($post->status, [ScheduledPostStatus::PENDING->value, ScheduledPostStatus::AWAITING_UPLOAD->value], true)) {
            throw ScheduledPostException::cannotCancel();
        }

        $post->update(['status' => ScheduledPostStatus::CANCELLED->value]);

        // O agendamento em várias contas reaproveita a mídia: só apaga se nenhum outro post ainda a usa.
        $this->storageService->deleteIfUnused($post->mediaPaths(), $post->id);
    }

    /**
     * Confirma o envio do vídeo: libera os posts `awaiting_upload` daquele path (no workspace corrente) para a fila.
     * Idempotente: posts que já saíram de `awaiting_upload` não são tocados nem despachados de novo.
     *
     * @return array{late: bool, message: string, posts: Collection}
     */
    public function completeUpload(string $mediaStoragePath): array
    {
        // O escopo de tenant isola: path de outro workspace não acha post (404).
        $all = ScheduledPost::where('media_path', $mediaStoragePath)->get();

        if ($all->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(ScheduledPost::class);
        }

        $awaiting = $all->where('status', ScheduledPostStatus::AWAITING_UPLOAD->value);

        if ($awaiting->isEmpty()) {
            // Só posts fechados sem envio confirmado (expirados e/ou cancelados): o prazo acabou, não há o que confirmar.
            if ($all->every(fn (ScheduledPost $post) => $post->upload_completed_at === null
                && in_array($post->status, [ScheduledPostStatus::FAILED->value, ScheduledPostStatus::CANCELLED->value], true))) {
                throw $all->contains('status', ScheduledPostStatus::FAILED->value)
                    ? ScheduledPostException::uploadDeadlinePassed()
                    : ScheduledPostException::uploadCancelled();
            }

            // Repetição (posts já confirmados): nada a liberar.
            return ['late' => false, 'message' => 'Envio do vídeo já confirmado.', 'posts' => $all->load($this->with)];
        }

        // O vídeo e a thumbnail de cada post precisam estar no storage (e o vídeo não pode estar vazio); senão os posts seguem aguardando.
        foreach ($awaiting as $post) {
            if (! $this->mediaStillExists($post, requireContent: true)) {
                throw ScheduledPostException::uploadNotFound();
            }
        }

        $released = DB::transaction(function () use ($awaiting) {
            $released = collect();

            foreach ($awaiting as $post) {
                // Trava a linha e confere o status de novo: duas confirmações simultâneas não despacham dois jobs.
                $locked = ScheduledPost::whereKey($post->id)->lockForUpdate()->first();

                if ($locked?->status !== ScheduledPostStatus::AWAITING_UPLOAD->value) {
                    continue;
                }

                $locked->update([
                    'status' => ScheduledPostStatus::PENDING->value,
                    'upload_completed_at' => now(),
                ]);

                $this->dispatchPlatformJob($locked);
                $released->push($locked);
            }

            return $released;
        });

        $late = $released->first(fn (ScheduledPost $post) => $post->scheduled_at && $post->upload_completed_at->gt($post->scheduled_at));

        foreach ($released as $post) {
            if ($post->scheduled_at && $post->upload_completed_at->gt($post->scheduled_at)) {
                app(NotificationService::class)->notifyPostUploadLate($post);
            }
        }

        return [
            'late' => $late !== null,
            'message' => $late
                ? 'O envio do vídeo terminou às '.$late->upload_completed_at->format('H:i').', depois do horário agendado ('.$late->scheduled_at->format('H:i').'). Estamos publicando agora.'
                : 'Envio do vídeo concluído.',
            'posts' => $all->fresh($this->with),
        ];
    }

    /**
     * Fecha como `failed` os posts que esperaram o envio do vídeo além do limite (a conta é por `created_at`).
     * Retorna quantos foram fechados.
     */
    public function expireAwaitingUploads(): int
    {
        $limit = now()->subHours((int) config('scheduling.upload_expiration_hours'));

        $ids = ScheduledPost::withoutGlobalScopes()
            ->where('status', ScheduledPostStatus::AWAITING_UPLOAD->value)
            ->where('created_at', '<', $limit)
            ->pluck('id');

        $expired = 0;

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired) {
                $post = ScheduledPost::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();

                // Um upload-complete que chegou no meio já liberou o post: não toca.
                if ($post?->status !== ScheduledPostStatus::AWAITING_UPLOAD->value) {
                    return;
                }

                $post->update([
                    'status' => ScheduledPostStatus::FAILED->value,
                    'error_message' => ScheduledPostError::UPLOAD_EXPIRED->message(),
                ]);

                $expired++;
            });
        }

        return $expired;
    }

    /**
     * Reenvia um post que falhou, com os mesmos dados.
     * Sem data o post sai na hora; com data, é reagendado.
     */
    public function retry(string $uuid, ?string $scheduledAt = null): ScheduledPost
    {
        $post = ScheduledPost::where('uuid', $uuid)->firstOrFail();

        if ($post->status !== ScheduledPostStatus::FAILED->value) {
            throw ScheduledPostException::cannotRetry();
        }

        // Posts antigos não têm o controle de limpeza, então confere no storage também.
        if (! $post->canRetry() || ! $this->mediaStillExists($post)) {
            throw ScheduledPostException::mediaExpired();
        }

        // Se a rede já tem o vídeo (job que morreu antes de gravar), o post vira published e o reenvio é recusado.
        app(PostReconciliationService::class)->guardRetry($post);

        DB::transaction(function () use (&$post, $scheduledAt) {
            // Trava a linha e confere o status de novo: dois cliques simultâneos não despacham dois jobs.
            $locked = ScheduledPost::whereKey($post->id)->lockForUpdate()->first();

            if ($locked?->status !== ScheduledPostStatus::FAILED->value) {
                throw ScheduledPostException::cannotRetry();
            }

            $locked->update([
                'status' => ScheduledPostStatus::PENDING->value,
                'error_message' => null,
                'failed_at' => null,
                'scheduled_at' => $this->toAppTimezone($scheduledAt),
                'container_id' => null,
                'container_created_at' => null,
            ]);

            $post = $locked;
        });

        $this->dispatchPlatformJob($post);

        return $post->load($this->with);
    }

    /**
     * Data com fuso (ISO com `Z` ou `-03:00`) vira o mesmo instante no fuso da aplicação: gravar o relógio do
     * offset como se fosse local deslocaria o post. Data sem fuso já vale no fuso da aplicação.
     */
    private function toAppTimezone(?string $date): ?Carbon
    {
        return $date ? Carbon::parse($date)->setTimezone(config('app.timezone')) : null;
    }

    /** Vídeo e, quando houver, a thumbnail ainda precisam estar no storage (com `$requireContent`, também não podem estar vazios). */
    private function mediaStillExists(ScheduledPost $post, bool $requireContent = false): bool
    {
        foreach ($post->mediaPaths() as $path) {
            if (! $this->storageService->exists($path) || ($requireContent && $this->storageService->size($path) <= 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cria e agenda o post para uma conta social específica.
     */
    private function createPostForAccount(
        User $user,
        SocialAccount $account,
        string $mediaStoragePath,
        array $data,
        bool $awaitingUpload = false,
    ): ScheduledPost {
        $platform = Platform::from($account->platform);
        $payloadBuild = $this->payloadBuilderFactory->make($platform)->build($data);
        $attributes = Arr::except($payloadBuild->attributes(), ['social_account_uuids', 'media_storage_path', 'thumbnail_storage_path']);

        $postData = array_merge($attributes, [
            'user_id' => $user->id,
            'social_account_id' => $account->id,
            'platform' => $platform->value,
            'media_path' => $mediaStoragePath,
            'payload' => $payloadBuild->payload(),
            'status' => $awaitingUpload ? ScheduledPostStatus::AWAITING_UPLOAD->value : ScheduledPostStatus::PENDING->value,
            'scheduled_at' => $attributes['scheduled_at'] ?? null,
        ]);

        $post = $this->store($postData);

        if (! $awaitingUpload) {
            $this->dispatchPlatformJob($post);
        }

        return $post;
    }

    /**
     * Vincula o primeiro post gerado ao cartão da pipeline correspondente
     * e o move automaticamente para a etapa "agendado".
     */
    private function handlePipelineCard(?string $pipelineCardUuid, Collection $posts): void
    {
        if ($pipelineCardUuid && $posts->isNotEmpty()) {
            $card = ContentPipeline::where('uuid', $pipelineCardUuid)->first();

            if ($card) {
                $this->pipelineService->markAsScheduled($card, $posts->first());
            }
        }
    }

    private function ensureValidSocialAccount(User $user, string $socialAccountUuid): SocialAccount
    {
        // A conta pertence ao workspace: o global scope de tenant isola; user_id da conta não entra (spec 017).
        // O workspace corrente é filtrado explicitamente, além do global scope (defesa em profundidade).
        $account = SocialAccount::where('uuid', $socialAccountUuid)
            ->where('workspace_id', $user->resolveCurrentAccess()?->workspace_id)
            ->first();

        if (! $account) {
            throw ScheduledPostException::noAccountLinked();
        }

        // Conta sem o escopo de publicação (hoje só o TikTok): 422 no agendamento em vez de falhar na hora de publicar.
        if (! $account->canPublish()) {
            $label = Platform::from($account->platform)->label();

            throw ValidationException::withMessages([
                'social_account_uuids' => ["A conta do {$label} não tem permissão para publicar. Reconecte a conta do {$label}."],
            ]);
        }

        return $account;
    }

    private function dispatchPlatformJob(ScheduledPost $post): void
    {
        // afterCommit: o worker só pega o job depois que o post existe de fato (e nunca de um post com rollback).
        $dispatch = PublishPostJob::dispatch($post)->afterCommit();

        if ($post->scheduled_at) {
            $dispatch->delay($post->scheduled_at);
        }
    }
}
