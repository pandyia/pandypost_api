<?php

namespace App\Services\Analytics;

use App\Models\SocialAccount;
use App\Support\Analytics\AnalyticsPeriod;
use Carbon\CarbonImmutable;

/**
 * Leitura do histórico de snapshots diários (Instagram e TikTok), para as
 * métricas que as APIs não guardam: seguidores e totais ao longo do tempo.
 */
class SnapshotHistory
{
    /**
     * Valor do campo no fim do dia anterior a $date (base para "ganhos no período").
     */
    public function valueBefore(SocialAccount $account, string $field, CarbonImmutable $date): ?int
    {
        return $account->metricSnapshots()
            ->onOrBefore($date->subDay()->toDateString())
            ->value($field);
    }

    /**
     * Quanto o campo variou no período: valor final − valor antes do início.
     * Só existe com snapshot nas duas pontas ($endValue = valor atual da API ou do último snapshot).
     */
    public function delta(SocialAccount $account, string $field, CarbonImmutable $start, CarbonImmutable $end, ?int $endValue = null): ?int
    {
        $before = $this->valueBefore($account, $field, $start);
        $after = $endValue ?? $account->metricSnapshots()->onOrBefore($end->toDateString())->value($field);

        if ($before === null || $after === null) {
            return null;
        }

        return $after - $before;
    }

    /**
     * Série diária do campo no período (null nos dias sem snapshot), ou null se não há nenhum.
     */
    public function dailySeries(SocialAccount $account, string $field, AnalyticsPeriod $period): ?array
    {
        $values = $account->metricSnapshots()
            ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
            ->get(['date', $field])
            ->mapWithKeys(fn ($snapshot) => [$snapshot->date->toDateString() => $snapshot->{$field}]);

        if ($values->isEmpty()) {
            return null;
        }

        return array_map(fn (string $date) => $values[$date] ?? null, $period->buckets());
    }
}
