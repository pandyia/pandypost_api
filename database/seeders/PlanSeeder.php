<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Plano b sico para come ar',
                'monthly_posts_limit' => 5,
                'social_accounts_limit' => 1,
                'price' => 0.00,
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Ideal para criadores de conte do',
                'monthly_posts_limit' => 100,
                'social_accounts_limit' => 10,
                'price' => 99.90,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate([
                'slug' => $plan['slug'],
            ], $plan);
        }
    }
}