<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Jobs\SnapshotSocialAccountMetricsJob;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

class SnapshotSocialAccountsCommand extends Command
{
    protected $signature = 'analytics:snapshot';

    protected $description = 'Agenda o snapshot diário das métricas das contas Instagram e TikTok';

    public function handle(): void
    {
        $count = 0;

        // Sem usuário autenticado o escopo de workspace não se aplica: percorre todas as contas.
        SocialAccount::whereIn('platform', [Platform::INSTAGRAM->value, Platform::TIKTOK->value])
            ->lazyById()
            ->each(function (SocialAccount $account) use (&$count) {
                SnapshotSocialAccountMetricsJob::dispatch($account);
                $count++;
            });

        $this->info("Snapshot agendado para {$count} conta(s).");
    }
}
