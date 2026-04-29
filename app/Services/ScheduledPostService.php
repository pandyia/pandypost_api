<?php

namespace App\Services;

use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Exceptions\ScheduledPostException;
use App\Models\SocialAccount;
use App\Exceptions\SubscriptionException;
use App\Models\ScheduledPost;
use App\Services\Factories\PayloadBuilderFactory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use App\Jobs\PublishPostJob;

class ScheduledPostService extends BaseService
{
    protected array $with = ['socialAccount'];

    public function __construct(
        ScheduledPost $scheduledPost,
        private readonly PayloadBuilderFactory $payloadBuilderFactory,
    )
    {
        parent::__construct($scheduledPost);
    }

    public function schedule(User $user, array $data, UploadedFile $video, ?UploadedFile $thumbnail = null): Collection
    {
        $this->ensureValidSubscription($user);

        $accountUuids = $data['social_account_uuids'];
        $videoPath    = Storage::putFile('videos', $video);

        $user->subscription->increment('posts_used', count($accountUuids));

        $posts = collect($accountUuids)->map(function (string $uuid) use ($user, $videoPath, $data, $thumbnail) {
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
        });

        return $posts;
    }

    private function ensureValidSubscription(User $user): void
    {
        $subscription = $user->subscription;

        if (!$subscription || !$subscription->isValid()) {
            throw SubscriptionException::subscriptionInactive();
        }

        if (!$subscription->hasQuota()) {
            throw SubscriptionException::quotaExceeded();
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
