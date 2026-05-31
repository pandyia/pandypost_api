<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Exceptions\SubscriptionException;
use Illuminate\Support\Facades\DB;

class SubscriptionService extends BaseService
{
    public function __construct(Subscription $subscription)
    {
        parent::__construct($subscription);
    }

    public function ensureValidSubscription(User $user): void
    {
        $subscription = $user->subscription;

        if (!$subscription || !$subscription->isValid()) {
            throw SubscriptionException::subscriptionInactive();
        }

        if (!$subscription->hasQuota()) {
            throw SubscriptionException::quotaExceeded();
        }
    }

    public function consumeQuota(User $user, int $amount): void
    {
        $user->subscription->increment('posts_used', $amount);
    }

    public function subscribe(User $user, int $planId): array
    {
        return DB::transaction(function () use ($user, $planId) {
            $subscription = Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_id' => $plan->id,
                    'starts_at' => now(),
                    'status' => 'active',
                    'posts_limit' => $plan->monthly_posts_limit,
                    'posts_used' => 0,
                ]
            );

            return [
                'plan_name' => $plan->name,
                'posts_limit' => $subscription->posts_limit
            ];
        });
    }
}