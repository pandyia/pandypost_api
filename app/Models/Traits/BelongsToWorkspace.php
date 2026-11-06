<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (! auth()->hasUser()) {
                return;
            }

            $access = auth()->user()->currentAccess;

            // Falha fechado: usuário autenticado sem workspace corrente não enxerga dado de tenant nenhum.
            // Sem usuário (jobs, comandos, webhooks) o escopo não se aplica; quem precisa dele filtra explicitamente.
            if (! $access) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $workspaceRelation = (new static)->workspaceRelation ?? null;

            if ($workspaceRelation) {
                // Indireto: filtra via relacionamento (ex: User → accesses)
                $builder->whereHas($workspaceRelation, fn ($q) => $q->where('workspace_id', $access->workspace_id));
            } else {
                // Direto: filtra pela coluna workspace_id na própria tabela
                $builder->where('workspace_id', $access->workspace_id);
            }
        });
    }
}
