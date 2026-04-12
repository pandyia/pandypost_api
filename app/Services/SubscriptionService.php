<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService extends BaseService
{
    public function __construct(Subscription $subscription)
    {
        parent::__construct($subscription);
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