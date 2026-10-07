<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Models\SocialAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

// O token do Instagram vence em 60 dias e não renova depois de vencido; renovar antes mantém a conta conectada.
class RefreshInstagramTokensCommand extends Command
{
    protected $signature = 'instagram:refresh-tokens';

    protected $description = 'Renova os tokens do Instagram que vencem nos próximos dias';

    private const RENEW_WITHIN_DAYS = 7;

    public function handle(): void
    {
        SocialAccount::query()
            ->where('platform', Platform::INSTAGRAM->value)
            ->whereBetween('expires_at', [now(), now()->addDays(self::RENEW_WITHIN_DAYS)])
            ->each(function (SocialAccount $account) {
                try {
                    $account->refreshToken();
                    $this->info("Token do Instagram da conta {$account->id} renovado.");
                } catch (Throwable $e) {
                    Log::warning("Não foi possível renovar o token do Instagram da conta {$account->id}: {$e->getMessage()}");
                }
            });
    }
}
