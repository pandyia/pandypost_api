<?php

namespace App\Jobs;

use App\Exceptions\AnalyticsException;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Grava a foto do dia das métricas de uma conta. Idempotente: rodar 2x no
 * mesmo dia só atualiza o registro do dia.
 */
class SnapshotSocialAccountMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [60, 300];

    public function __construct(public SocialAccount $account)
    {
        $this->onQueue($account->platform); // fila da plataforma
    }

    public function handle(AnalyticsService $analyticsService): void
    {
        try {
            $metrics = $analyticsService->snapshotMetrics($this->account);
        } catch (AnalyticsException $e) {
            // Conta sem permissão (precisa reconectar): não adianta tentar de novo.
            Log::warning("Snapshot da conta {$this->account->id} ignorado: {$e->getMessage()}");

            return;
        }

        if ($metrics === null) {
            return;
        }

        $this->account->metricSnapshots()->updateOrCreate(
            ['date' => now()->toDateString()],
            $metrics,
        );
    }
}
