<?php

namespace App\Observers;

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

            // FASE 2: a assinatura de "plano gratuito" foi removida do onboarding.
            // A assinatura passa a ser criada via Stripe Checkout (fase Cliente);
            // limites de plano estão deferidos.
        }
    }
}
