<?php

namespace App\Services;

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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduledPostService extends BaseService
{
    protected array $with = ['socialAccount'];

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

        $accountUuids = $data['social_account_uuids'];
        $pipelineCardUuid = Arr::pull($data, 'pipeline_card_uuid');
        $mediaStoragePath = $data['media_storage_path'];

        return DB::transaction(function () use ($user, $accountUuids, $pipelineCardUuid, $data, $mediaStoragePath) {
            $this->subscriptionService->consumeQuota($user, count($accountUuids));

            $posts = collect($accountUuids)->map(fn (string $uuid) => $this->createPostForAccount($user, $uuid, $mediaStoragePath, $data)
            );

            $this->handlePipelineCard($pipelineCardUuid, $posts); // TODO tem a ver com kanban, não implementado ainda no frontend.

            return $posts;
        });
    }

    /**
     * Cancela um post agendado.
     * Só é possível cancelar posts com status "pending".
     */
    public function cancel(User $user, string $uuid): void
    {
        $post = ScheduledPost::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($post->status !== ScheduledPostStatus::PENDING->value) {
            throw ScheduledPostException::cannotCancel();
        }

        $post->update(['status' => ScheduledPostStatus::CANCELLED->value]);

        $paths = [$post->media_path];
        $payload = $post->payload ?? [];
        if (! empty($payload['thumbnail_path'])) {
            $paths[] = $payload['thumbnail_path'];
        }

        $this->storageService->deleteMany(array_filter($paths));
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
                'scheduled_at' => $scheduledAt,
                'container_id' => null,
                'container_created_at' => null,
            ]);

            $post = $locked;
        });

        $this->dispatchPlatformJob($post);

        return $post->load($this->with);
    }

    /** Vídeo e, quando houver, a thumbnail ainda precisam estar no storage. */
    private function mediaStillExists(ScheduledPost $post): bool
    {
        $paths = array_filter([$post->media_path, $post->payload['thumbnail_path'] ?? null]);

        foreach ($paths as $path) {
            if (! $this->storageService->exists($path)) {
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
        string $uuid,
        string $mediaStoragePath,
        array $data,
    ): ScheduledPost {
        $account = $this->ensureValidSocialAccount($user, $uuid);
        $platform = Platform::from($account->platform);
        $payloadBuild = $this->payloadBuilderFactory->make($platform)->build($data);
        $attributes = Arr::except($payloadBuild->attributes(), ['social_account_uuids', 'media_storage_path', 'thumbnail_storage_path']);

        $postData = array_merge($attributes, [
            'user_id' => $user->id,
            'social_account_id' => $account->id,
            'platform' => $platform->value,
            'media_path' => $mediaStoragePath,
            'payload' => $payloadBuild->payload(),
            'status' => ScheduledPostStatus::PENDING->value,
            'scheduled_at' => $attributes['scheduled_at'] ?? null,
        ]);

        $post = $this->store($postData);
        $this->dispatchPlatformJob($post);

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
        $account = SocialAccount::where('user_id', $user->id)
            ->where('uuid', $socialAccountUuid)
            ->first();

        if (! $account) {
            throw ScheduledPostException::noAccountLinked($socialAccountUuid);
        }

        return $account;
    }

    private function dispatchPlatformJob(ScheduledPost $post): void
    {
        $post->scheduled_at
            ? PublishPostJob::dispatch($post)->delay($post->scheduled_at)
            : PublishPostJob::dispatch($post);
    }
}
