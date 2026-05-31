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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

    public function schedule(User $user, array $data, UploadedFile $video, ?UploadedFile $thumbnail = null): Collection
    {
        $this->subscriptionService->ensureValidSubscription($user);

        $accountUuids     = $data['social_account_uuids'];
        $pipelineCardUuid = Arr::pull($data, 'pipeline_card_uuid');

        return DB::transaction(function () use ($user, $accountUuids, $pipelineCardUuid, $data, $video, $thumbnail) {
            $this->subscriptionService->consumeQuota($user, count($accountUuids));

            $videoPath = Storage::putFile('videos', $video);

            $posts = collect($accountUuids)->map(fn (string $uuid) =>
                $this->createPostForAccount($user, $uuid, $videoPath, $data, $thumbnail)
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
        string $videoPath,
        array $data,
        ?UploadedFile $thumbnail
    ): ScheduledPost {
        $account      = $this->ensureValidSocialAccount($user, $uuid);
        $platform     = Platform::from($account->platform);
        $payloadBuild = $this->payloadBuilderFactory->make($platform)->build($data, $thumbnail);
        $attributes   = Arr::except($payloadBuild->attributes(), ['social_account_uuids']);

        $postData = array_merge($attributes, [
            'user_id'           => $user->id,
            'social_account_id' => $account->id,
            'platform'          => $platform->value,
            'media_path'        => $videoPath,
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
