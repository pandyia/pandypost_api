<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (auth()->hasUser() && !auth()->user()->isSuperAdmin()) {
                $access = auth()->user()->currentAccess;
                if ($access) {
                    $workspaceRelation = (new static)->workspaceRelation ?? null;

                    if ($workspaceRelation) {
                        // Indireto: filtra via relacionamento (ex: User → accesses)
                        $builder->whereHas($workspaceRelation, fn($q) => $q->where('workspace_id', $access->workspace_id));
                    } else {
                        // Direto: filtra pela coluna workspace_id na própria tabela
                        $builder->where('workspace_id', $access->workspace_id);
                    }
                }
            }
        });
    }
}
