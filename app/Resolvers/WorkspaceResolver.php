<?php

namespace App\Resolvers;

use App\Models\User;
use App\Models\Workspace;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

class WorkspaceResolver implements Resolver
{
    /**
     * Resolve o workspace_id de forma genérica:
     * 1. Tenta via sessão autenticada (currentAccess).
     * 2. Se o model auditado É um Workspace → usa o id dele.
     * 3. Se o model auditado TEM coluna workspace_id → usa direto.
     * 4. Se o model auditado TEM relação workspace() → carrega e usa.
     */
    public static function resolve(Auditable $auditable): ?int
    {
        // Se o model for um Workspace e estiver sendo deletado, não podemos associar
        // o audit ao workspace_id pois o registro pai (workspace) já foi removido do banco.
        if ($auditable instanceof Workspace && $auditable->getAuditEvent() === 'deleted') {
            return null;
        }

        // 1. Sessão autenticada
        $user = auth()->user() ?? auth()->guard('sanctum')->user();

        if ($user && $user->currentAccess) {
            return $user->currentAccess->workspace_id;
        }

        // 2. O model É um Workspace
        if ($auditable instanceof Workspace) {
            return $auditable->id;
        }

        // 3. O model TEM coluna workspace_id
        if (!empty($auditable->workspace_id)) {
            return $auditable->workspace_id;
        }

        // 4. O model TEM relação workspace()
        if (method_exists($auditable, 'workspace')) {
            return $auditable->workspace?->id;
        }

        return null;
    }
}
