<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class PruneNotificationsCommand extends Command
{
    public const RETENTION_DAYS = 30;

    protected $signature = 'notifications:prune';

    protected $description = 'Remove notificações lidas há mais de 30 dias (as não lidas nunca são apagadas)';

    public function handle(): void
    {
        $deleted = Notification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->delete();

        if ($deleted > 0) {
            $this->info("Removidas {$deleted} notificação(ões) lida(s) há mais de ".self::RETENTION_DAYS.' dias.');
        }
    }
}
