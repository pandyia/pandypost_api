<?php

namespace App\Console\Commands;

use App\Services\ScheduledPostService;
use Illuminate\Console\Command;

class ExpireAwaitingUploadsCommand extends Command
{
    protected $signature = 'posts:expire-awaiting-uploads';

    protected $description = 'Fecha como failed os posts que ficaram aguardando o envio do vídeo além do limite (spec 021)';

    public function handle(ScheduledPostService $service): void
    {
        $expired = $service->expireAwaitingUploads();

        if ($expired > 0) {
            $this->info("Envios expirados: {$expired}");
        }
    }
}
