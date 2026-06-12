<?php

namespace App\Observers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkspaceService;

class UserObserver
{
    public function __construct(
        private WorkspaceService $workspaceService
    ) {
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('email_verified_at') && $user->email_verified_at !== null) {
            $data = [
                'name' => "{$user->name} Personal Team",
                'role_name' => 'Administrador',
                'permissions' => Role::adminPermissions(),
                'is_personal_team' => true,
            ];
            $this->workspaceService->createAllAccess($user, $data);

            // Criar assinatura padrão (plano gratuito) para o usuário agendar posts
            $freePlan = Plan::where('slug', 'free')->first();
            if ($freePlan) {
                Subscription::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'plan_id' => $freePlan->id,
                        'starts_at' => now(),
                        'status' => 'active',
                        'posts_limit' => $freePlan->monthly_posts_limit,
                        'posts_used' => 0,
                    ]
                );
            }
        }
    }
}
