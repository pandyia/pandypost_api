<?php

namespace App\Services;

use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Exceptions\ScheduledPostException;
use App\Models\ContentPipeline;
use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use App\Services\Factories\PayloadBuilderFactory;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use App\Jobs\PublishPostJob;
use Illuminate\Support\Facades\DB;

class ScheduledPostService extends BaseService
{
    protected array $with = ['socialAccount'];

    public function __construct(
        ScheduledPost $scheduledPost,
        private readonly PayloadBuilderFactory $payloadBuilderFactory,
        private readonly ContentPipelineService $pipelineService,
        private readonly SubscriptionService $subscriptionService,
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

        $accountUuids     = $data['social_account_uuids'];
        $pipelineCardUuid = Arr::pull($data, 'pipeline_card_uuid');
        $mediaStoragePath = $data['media_storage_path'];

        return DB::transaction(function () use ($user, $accountUuids, $pipelineCardUuid, $data, $mediaStoragePath) {
            $this->subscriptionService->consumeQuota($user, count($accountUuids));

            $posts = collect($accountUuids)->map(fn (string $uuid) =>
                $this->createPostForAccount($user, $uuid, $mediaStoragePath, $data)
            );

            $this->handlePipelineCard($pipelineCardUuid, $posts); //TODO tem a ver com kanban, não implementado ainda no frontend.

            return $posts;
        });
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
        $account      = $this->ensureValidSocialAccount($user, $uuid);
        $platform     = Platform::from($account->platform);
        $payloadBuild = $this->payloadBuilderFactory->make($platform)->build($data);
        $attributes   = Arr::except($payloadBuild->attributes(), ['social_account_uuids', 'media_storage_path', 'thumbnail_storage_path']);

        $postData = array_merge($attributes, [
            'user_id'           => $user->id,
            'social_account_id' => $account->id,
            'platform'          => $platform->value,
            'media_path'        => $mediaStoragePath,
            'payload'           => $payloadBuild->payload(),
            'status'            => ScheduledPostStatus::PENDING->value,
            'scheduled_at'      => $attributes['scheduled_at'] ?? null,
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

        if (!$account) {
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
