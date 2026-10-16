<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsDashboardRequest;
use App\Http\Resources\AnalyticsDashboardResource;
use App\Http\Resources\BestTimesResource;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;

/**
 * @group Analytics
 *
 * Métricas das contas conectadas, no **mesmo formato** para YouTube, Instagram e TikTok.
 * Métrica que a plataforma não oferece simplesmente não aparece.
 *
 * **Permissão:** nenhuma além de ser membro do workspace (VULN-06 em `specs/002`, em aberto).
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    /**
     * Dashboard da conta
     *
     * Monte a interface a partir da resposta:
     *
     * - **Filtros de período:** `capabilities.date_ranges` (o que a plataforma aceita) e `capabilities.max_range_days`.
     * - **Cards:** `overview`. `previous` e `trend` (variação %) podem ser `null` quando não há base de comparação.
     * - **Gráfico:** `time_series.dates` + `series[].current` / `series[].previous`.
     * - **Formato dos números:** campo `format` = `number` | `percent` | `duration` (segundos) | `hours` | `currency`.
     * - **Avisos:** `alerts` (`type`: `success`, `warning`, `danger`, `info`) e `notes` (textos fixos da plataforma).
     *
     * Períodos por plataforma: YouTube `last_7_days`, `last_28_days`, `last_90_days`, `this_month`, `last_month`,
     * `last_365_days`, `lifetime`, `custom`; Instagram `last_7_days`, `last_28_days`, `custom` (máx. 30 dias);
     * TikTok `last_7_days`, `last_28_days`, `last_90_days`, `custom` (máx. 365 dias).
     *
     * Resultado em cache (YouTube 6h, Instagram 3h, TikTok 1h).
     *
     * @urlParam socialAccount_uuid string required UUID da conta social. Example: 5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @queryParam date_range string Período. Padrão `last_28_days`. Example: last_28_days
     * @queryParam start_date string Obrigatório com `date_range=custom` (`Y-m-d`). Example: 2026-09-01
     * @queryParam end_date string Obrigatório com `date_range=custom` (`Y-m-d`), até hoje. Example: 2026-09-28
     * @queryParam refresh boolean `1` ignora o cache. No máximo 1 vez a cada 10 minutos por conta. Example: 0
     *
     * @response 200 {
     *   "data": {
     *     "platform": "instagram",
     *     "period": {"start": "2026-09-16", "end": "2026-10-13", "previous_start": "2026-08-19", "previous_end": "2026-09-15", "granularity": "day", "date_range": "last_28_days"},
     *     "capabilities": {"date_ranges": ["last_7_days", "last_28_days", "custom"], "max_range_days": 30, "has_daily_series": true, "has_revenue": false, "has_watch_time": false},
     *     "overview": [
     *       {"key": "views", "label": "Visualizações", "format": "number", "value": 12034, "previous": 9800, "trend": 22.8, "description": "Total de visualizações no período."}
     *     ],
     *     "time_series": {
     *       "dates": ["2026-09-16", "2026-09-17"],
     *       "series": [{"key": "reach", "label": "Alcance", "format": "number", "type": "area", "current": [120, 98], "previous": [80, 101]}]
     *     },
     *     "breakdowns": [{"key": "content_type", "label": "Visualizações por formato", "items": [{"key": "REEL", "label": "Reels", "value": 900}]}],
     *     "top_content": {
     *       "columns": [{"key": "views", "label": "Visualizações", "format": "number"}],
     *       "items": [{"id": "1789...", "title": "Bastidores", "thumbnail_url": "https://...", "permalink": "https://www.instagram.com/p/...", "published_at": "2026-09-20T18:00:00Z", "format": "reel", "metrics": {"views": 1000, "engagement_rate": 4.2}}]
     *     },
     *     "score": {"value": 72, "engagement": 40, "growth": 20, "frequency": 12},
     *     "alerts": [{"type": "success", "title": "Visualizações em alta", "message": "Suas visualizações subiram 22,8% em relação ao período anterior."}],
     *     "notes": ["Os dados do Instagram podem ter atraso de até 48 horas."],
     *     "generated_at": "2026-10-14T18:00:00Z"
     *   }
     * }
     *
     * @responseField score object Nota da conta (0–100) = `engagement` (até 50) + `growth` (até 30) + `frequency` (até 20).
     *
     * @response 409 scenario="reconectar conta" {"error": "analytics_reauth_required", "message": "Reconecte a conta do Instagram para liberar a leitura das métricas."}
     * @response 502 scenario="plataforma fora do ar" {"error": "analytics_provider_unavailable", "message": "Não foi possível carregar as métricas do Instagram agora. Tente novamente em alguns minutos."}
     * @response 429 scenario="refresh repetido" {"error": "analytics_refresh_too_soon", "message": "Você já atualizou os dados recentemente. Tente novamente em 7 minutos."}
     * @response 422 scenario="período inválido" {"message": "Período não disponível para esta plataforma.", "errors": {"date_range": ["Período não disponível para esta plataforma."]}}
     *
     * No 409, mostre o botão "Reconectar conta" (fluxo de [Contas sociais](#contas-sociais)).
     */
    public function dashboard(AnalyticsDashboardRequest $request, SocialAccount $socialAccount): JsonResponse
    {
        $data = $request->validated();
        $period = $this->analyticsService->resolvePeriod($socialAccount, $data);

        $dashboard = $this->analyticsService->dashboard(
            $socialAccount,
            $period,
            $request->user(),
            (bool) ($data['refresh'] ?? false),
        );

        return (new AnalyticsDashboardResource($dashboard))->response();
    }

    /**
     * Melhores horários para postar
     *
     * Média de visualizações por hora de publicação dos últimos conteúdos (até 50), no fuso `America/Sao_Paulo`.
     * Com menos de 5 conteúdos, `best_hours` é uma sugestão padrão e `is_fallback` vem `true`.
     *
     * @urlParam socialAccount_uuid string required UUID da conta social. Example: 5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 200 {
     *   "data": {
     *     "best_hours": [18, 20, 12],
     *     "by_hour": [{"hour": 0, "average": 0, "posts": 0}, {"hour": 18, "average": 1520.5, "posts": 6}],
     *     "metric": "views",
     *     "sample_size": 37,
     *     "is_fallback": false,
     *     "timezone": "America/Sao_Paulo"
     *   }
     * }
     *
     * @responseField by_hour object[] Sempre 24 posições (horas 0 a 23).
     */
    public function bestTimes(SocialAccount $socialAccount): JsonResponse
    {
        return (new BestTimesResource($this->analyticsService->bestTimes($socialAccount)))->response();
    }
}
