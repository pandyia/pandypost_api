<?php

namespace App\Console\Commands;

use App\Models\Audit;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audits:prune {--months=6 : Quantidade de meses para manter}';
    protected $description = 'Remove logs de auditoria mais antigos que o período configurado';

    public function handle(): void
    {
        $months = (int) $this->option('months');
        $cutoff = now()->subMonths($months);

        $deleted = Audit::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->delete();

        if ($deleted > 0) {
            $this->info("Removidos {$deleted} log(s) de auditoria anteriores a {$cutoff->format('d/m/Y')}.");
        } else {
            $this->info('Nenhum log antigo para remover.');
        }
    }
}
