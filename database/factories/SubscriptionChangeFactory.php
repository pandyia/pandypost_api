<?php

namespace Database\Factories;

use App\Enums\SubscriptionChangeStatus;
use App\Models\SubscriptionChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionChange>
 */
class SubscriptionChangeFactory extends Factory
{
    protected $model = SubscriptionChange::class;

    public function definition(): array
    {
        return [
            'subscription_id' => null, // deve ser informado no teste
            'price_id' => null, // deve ser informado no teste
            'stripe_schedule_id' => 'sch_'.fake()->unique()->bothify('????????????'),
            'effective_at' => now()->addDays(15),
            'status' => SubscriptionChangeStatus::PENDING,
        ];
    }

    public function applied(): static
    {
        return $this->state(['status' => SubscriptionChangeStatus::APPLIED]);
    }

    public function canceled(): static
    {
        return $this->state(['status' => SubscriptionChangeStatus::CANCELED]);
    }
}
