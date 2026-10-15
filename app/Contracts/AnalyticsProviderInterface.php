<?php

namespace App\Contracts;

use App\Models\SocialAccount;
use App\Support\Analytics\AnalyticsPeriod;

/**
 * Contrato de cada rede social no analytics (Strategy, resolvida pelo
 * AnalyticsProviderFactory). O provider só conversa com a API da plataforma
 * e normaliza os dados; cache, período e nota ficam no AnalyticsService.
 *
 * Erros de permissão do token devem virar AnalyticsException::reauthRequired().
 */
interface AnalyticsProviderInterface
{
    /**
     * Períodos aceitos e recursos da plataforma:
     * ['date_ranges' => [...], 'max_range_days' => ?int, 'has_daily_series' => bool,
     *  'has_revenue' => bool, 'has_watch_time' => bool, 'engagement_benchmark' => float,
     *  'best_times_metric' => 'views'|'interactions', 'cache_minutes' => int, 'lifetime_start' => ?string]
     */
    public function capabilities(): array;

    /**
     * Seções do dashboard: overview, time_series, breakdowns, top_content e notes.
     */
    public function dashboard(SocialAccount $account, string $token, AnalyticsPeriod $period): array;

    /**
     * Conteúdos recentes para o cálculo de melhores horários:
     * [['published_at' => CarbonImmutable, 'value' => int], ...]
     */
    public function recentContents(SocialAccount $account, string $token): array;

    /**
     * Métricas da conta para o snapshot diário, ou null quando a plataforma já
     * guarda o histórico (YouTube): ['followers', 'following', 'media_count', 'total_likes'].
     */
    public function snapshot(SocialAccount $account, string $token): ?array;
}
