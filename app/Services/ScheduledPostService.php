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

    public function schedule(User $user, array $data, UploadedFile $video, ?UploadedFile $thumbnail = null): ScheduledPost
    {
        $this->ensureValidSubscription($user);
        
        $platform = Platform::from($data['platform'] ?? '');
        $socialAccountUuid = $data['social_account_uuid'] ?? null;
        $account = $this->ensureValidSocialAccount($user, $platform->value, $socialAccountUuid);

        $videoPath = Storage::putFile('videos', $video);
        $payloadBuild = $this->payloadBuilderFactory->make($platform)->build($data, $thumbnail);
        $attributes = Arr::except($payloadBuild->attributes(), ['social_account_uuid']);

        $user->subscription->increment('posts_used');

        $postData = array_merge($attributes, [
            'user_id' => $user->id,
            'social_account_id' => $account->id,
            'media_path' => $videoPath,
            'payload' => $payloadBuild->payload(),
            'status' => ScheduledPostStatus::PENDING->value,
            'scheduled_at' => $attributes['scheduled_at'] ?? null,
        ]);

        $post = $this->store($postData);
        $this->dispatchPlatformJob($post);

        return $post;
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

    private function ensureValidSocialAccount(User $user, string $platform, ?string $socialAccountUuid): SocialAccount
    {
        $account = SocialAccount::where('user_id', $user->id)
            ->where('uuid', $socialAccountUuid)
            ->first();

        if (!$account) {
            throw ScheduledPostException::noAccountLinked($platform);
        }

        if ($account->platform !== $platform) {
            throw ScheduledPostException::invalidAccountSelection($platform);
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
