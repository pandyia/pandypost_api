<?php

namespace App\Services\OAuthProviders\Concerns;

use App\Models\User;
use App\Models\Workspace;

trait ResolvesAccountOwner
{
    /**
     * Dono da conta: no callback não há usuário logado, então ela fica com o primeiro membro do workspace.
     *
     * @return array{0: int, 1: ?int} [workspace_id, user_id]
     */
    private function accountOwner(User|Workspace $context): array
    {
        return $context instanceof Workspace
            ? [$context->id, $context->accesses()->first()?->user_id]
            : [$context->currentAccess->workspace_id, $context->id];
    }
}
