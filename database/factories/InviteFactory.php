<?php

namespace Database\Factories;

use App\Models\Invite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invite>
 */
class InviteFactory extends Factory
{
    protected $model = Invite::class;

    public function definition(): array
    {
        return [
            'email'        => fake()->unique()->safeEmail(),
            'workspace_id' => null, // deve ser informado no teste
            'invited_by'   => null, // deve ser informado no teste
            'role_id'      => null, // deve ser informado no teste
            'status'       => Invite::STATUS_PENDING,
            'expires_at'   => now()->addDays(3),
        ];
    }

    // -----------------------------------------------------------------------
    // States
    // -----------------------------------------------------------------------

    public function pending(): static
    {
        return $this->state(fn () => [
            'status'     => Invite::STATUS_PENDING,
            'expires_at' => now()->addDays(3),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => Invite::STATUS_ACCEPTED,
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'status' => Invite::STATUS_DECLINED,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status'     => Invite::STATUS_PENDING,
            'expires_at' => now()->subDay(),
        ]);
    }
}
