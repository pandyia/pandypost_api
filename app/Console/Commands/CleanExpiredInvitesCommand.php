<?php

namespace App\Console\Commands;

use App\Models\Invite;
use Illuminate\Console\Command;

class CleanExpiredInvitesCommand extends Command
{
    protected $signature = 'invites:clean-expired';
    protected $description = 'Marca convites expirados e remove registros antigos';

    public function handle(): void
    {
        $marked = Invite::expired()
            ->update(['status' => Invite::STATUS_EXPIRED]);

        if ($marked > 0) {
            $this->info("Marcados {$marked} convite(s) como expirado(s).");
        }

        $deleted = Invite::expiredDeletable()->delete();

        if ($deleted > 0) {
            $this->info("Removidos {$deleted} registro(s) expirado(s) há mais de 30 dias.");
        }
    }
}
