<?php

namespace App\Console\Commands;

use App\Services\PostReconciliationService;
use Illuminate\Console\Command;

class CloseStuckPostsCommand extends Command
{
    protected $signature = 'posts:close-stuck';

    protected $description = 'Confere na rede os posts parados em processing e os fecha como published ou failed (spec 020)';

    public function handle(PostReconciliationService $service): void
    {
        $result = $service->run();

        if (array_sum($result) > 0) {
            $this->info("Publicados: {$result['published']} · Falhos: {$result['failed']} · Aguardando: {$result['waiting']}");
        }
    }
}
