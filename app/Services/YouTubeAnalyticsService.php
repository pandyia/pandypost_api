<?php

namespace App\Services;

use App\Models\SocialAccount;
use Google\Client;
use Google\Service\YouTubeAnalytics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class YouTubeAnalyticsService
{
    /**
     * Get dashboard data for a given social account and date range.
     * Caches the result to avoid quota limits.
     */
    public function getDashboardData(SocialAccount $account, string $dateRange): array
    {
        // 1. Determine date ranges
        $dates = $this->calculateDateRange($dateRange);
        $startDate = $dates['current']['start']->format('Y-m-d');
        $endDate = $dates['current']['end']->format('Y-m-d');
        
        $prevStartDate = $dates['previous']['start']->format('Y-m-d');
        $prevEndDate = $dates['previous']['end']->format('Y-m-d');

        // Determine dimension based on date range (use months if > 365 days to avoid too many points)
        $diffDays = $dates['current']['start']->diffInDays($dates['current']['end']);
        $dimension = $diffDays > 365 ? 'month' : 'day';

        // Cache key based on account and dates (updated to v2 to bypass previous empty cache)
        $cacheKey = "youtube_analytics_v2_{$account->id}_{$startDate}_{$endDate}_{$dimension}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($account, $startDate, $endDate, $prevStartDate, $prevEndDate, $dimension) {
            $client = $this->getAuthenticatedClient($account);
            $service = new YouTubeAnalytics($client);

            // Fetch current period data
            $currentOverview = $this->fetchOverviewMetrics($service, $startDate, $endDate);
            $previousOverview = $this->fetchOverviewMetrics($service, $prevStartDate, $prevEndDate);

            // Fetch time series
            $currentSeries = $this->fetchTimeSeries($service, $startDate, $endDate, $dimension);
            $previousSeries = $this->fetchTimeSeries($service, $prevStartDate, $prevEndDate, $dimension);

            // Fetch top videos
            $topVideos = $this->fetchTopVideos($service, $startDate, $endDate);

            // Fetch traffic sources
            $trafficSources = $this->fetchTrafficSources($service, $startDate, $endDate);

            return [
                'overviewMetrics' => $this->buildOverviewMetrics($currentOverview, $previousOverview),
                'timeSeriesData' => $this->buildTimeSeriesData($currentSeries, $previousSeries, $startDate, $endDate, $dimension),
                'trafficSources' => $this->buildTrafficSources($trafficSources),
                'topVideos' => $topVideos,
                'alerts' => $this->generateAlerts($topVideos, $currentOverview, $previousOverview),
            ];
        });
    }

    private function getAuthenticatedClient(SocialAccount $account): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        
        // Refresh token if necessary using the existing logic in SocialAccount
        $token = $account->getValidToken();
        $client->setAccessToken($token);

        return $client;
    }

    private function calculateDateRange(string $dateRange): array
    {
        $now = Carbon::now();
        
        switch ($dateRange) {
            case 'lifetime':
                $start = Carbon::create(2005, 2, 14); // Data de criação do YouTube
                $end = $now->copy();
                // Para o período anterior de "todo o período", não há comparação exata, mantemos os mesmos valores para zerar a variação
                $prevStart = $start->copy();
                $prevEnd = $end->copy();
                break;
            case 'last_28_days':
                $start = $now->copy()->subDays(28);
                $end = $now->copy();
                $prevStart = $now->copy()->subDays(56);
                $prevEnd = $now->copy()->subDays(28);
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy();
                $prevStart = $now->copy()->subMonth()->startOfMonth();
                $prevEnd = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                $prevStart = $now->copy()->subMonths(2)->startOfMonth();
                $prevEnd = $now->copy()->subMonths(2)->endOfMonth();
                break;
            case 'last_7_days':
            default:
                $start = $now->copy()->subDays(7);
                $end = $now->copy();
                $prevStart = $now->copy()->subDays(14);
                $prevEnd = $now->copy()->subDays(7);
                break;
        }

        return [
            'current' => ['start' => $start, 'end' => $end],
            'previous' => ['start' => $prevStart, 'end' => $prevEnd],
        ];
    }

    private function fetchOverviewMetrics(YouTubeAnalytics $service, string $startDate, string $endDate): array
    {
        try {
            $optParams = [
                'ids' => 'channel==MINE',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'metrics' => 'views,estimatedMinutesWatched,subscribersGained,subscribersLost,estimatedRevenue,averageViewDuration',
            ];

            $response = $service->reports->query($optParams);
            
            if (empty($response->getRows())) {
                return $this->emptyOverview();
            }

            $row = $response->getRows()[0];
            return [
                'views' => (int) $row[0],
                'estimatedMinutesWatched' => (int) $row[1],
                'netSubscribers' => (int) $row[2] - (int) $row[3],
                'estimatedRevenue' => (float) $row[4],
                'averageViewDuration' => (int) $row[5],
            ];
        } catch (Exception $e) {
            Log::error('YouTube Analytics API Error (Overview): ' . $e->getMessage());
            // Se for erro de permissão ou API desativada, não podemos engolir o erro, 
            // senão ele salva "0 views" no cache por 6 horas.
            if (str_contains($e->getMessage(), 'disabled') || str_contains($e->getMessage(), '403') || str_contains($e->getMessage(), 'Permission')) {
                throw $e;
            }
            return $this->emptyOverview();
        }
    }

    private function emptyOverview(): array
    {
        return [
            'views' => 0,
            'estimatedMinutesWatched' => 0,
            'netSubscribers' => 0,
            'estimatedRevenue' => 0.0,
            'averageViewDuration' => 0,
        ];
    }

    private function fetchTimeSeries(YouTubeAnalytics $service, string $startDate, string $endDate, string $dimension = 'day'): array
    {
        try {
            $optParams = [
                'ids' => 'channel==MINE',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'metrics' => 'views',
                'dimensions' => $dimension,
                'sort' => $dimension,
            ];

            $response = $service->reports->query($optParams);
            
            $data = [];
            if ($response->getRows()) {
                foreach ($response->getRows() as $row) {
                    $data[$row[0]] = (int) $row[1];
                }
            }
            return $data;
        } catch (Exception $e) {
            Log::error('YouTube Analytics API Error (TimeSeries): ' . $e->getMessage());
            return [];
        }
    }

    private function fetchTopVideos(YouTubeAnalytics $service, string $startDate, string $endDate): array
    {
        try {
            // Include annotationClickThroughRate as a proxy for CTR if impressions aren't available,
            // though standard views and watch time are primary.
            $optParams = [
                'ids' => 'channel==MINE',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'metrics' => 'views,estimatedMinutesWatched,averageViewDuration,subscribersGained,annotationClickThroughRate',
                'dimensions' => 'video',
                'sort' => '-views',
                'maxResults' => 10,
            ];

            $response = $service->reports->query($optParams);
            
            $videos = [];
            if ($response->getRows()) {
                foreach ($response->getRows() as $row) {
                    $videoId = $row[0];
                    $views = (int) $row[1];
                    $watchTimeMinutes = (int) $row[2];
                    $avgViewDurationSeconds = (int) $row[3];
                    $subscribersGained = (int) $row[4];
                    $ctr = (float) $row[5]; // Note: this is annotation CTR, standard impressions CTR isn't natively supported here usually

                    // Approximation of retention % (assuming we don't have exact video length easily here without Data API)
                    // We will mock retention % for the MVP if we can't get it directly, or calculate based on averageViewDuration if we had length.
                    $retention = min(100, ($avgViewDurationSeconds / max(1, $watchTimeMinutes * 60)) * 100); 

                    $videos[] = [
                        'id' => $videoId,
                        'title' => "Vídeo ID: {$videoId}", // Ideally we'd join with YouTube Data API for titles
                        'thumbnail' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
                        'views' => $views,
                        'watchTime' => $watchTimeMinutes,
                        'ctr' => round($ctr * 100, 1), 
                        'retention' => round($retention, 1),
                        'winnerScore' => $this->calculateWinnerScore($views, $watchTimeMinutes, $retention, $subscribersGained, $ctr)
                    ];
                }
            }
            return $videos;
        } catch (Exception $e) {
            Log::error('YouTube Analytics API Error (Top Videos): ' . $e->getMessage());
            return [];
        }
    }

    private function fetchTrafficSources(YouTubeAnalytics $service, string $startDate, string $endDate): array
    {
        try {
            $optParams = [
                'ids' => 'channel==MINE',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'metrics' => 'views',
                'dimensions' => 'insightTrafficSourceType',
                'sort' => '-views',
                'maxResults' => 5,
            ];

            $response = $service->reports->query($optParams);
            
            $labels = [];
            $series = [];
            
            if ($response->getRows()) {
                foreach ($response->getRows() as $row) {
                    $labels[] = $this->translateTrafficSource($row[0]);
                    $series[] = (int) $row[1];
                }
            }
            return ['labels' => $labels, 'series' => $series];
        } catch (Exception $e) {
            Log::error('YouTube Analytics API Error (Traffic Sources): ' . $e->getMessage());
            return ['labels' => [], 'series' => []];
        }
    }

    private function calculateWinnerScore(int $views, int $watchTime, float $retention, int $subscribers, float $ctr): int
    {
        // Ponderações (exemplo MVP): Retenção (30%), CTR (30%), Views (20%), WatchTime (10%), Inscritos (10%)
        // Como views e watchtime não tem limite máximo, normalizamos com logaritmo ou thresholds.
        
        $viewsScore = min(20, log($views + 1, 10) * 4); // Ex: 100k views = 5 * 4 = 20 pts
        $watchTimeScore = min(10, log($watchTime + 1, 10) * 2);
        
        // Retenção: máximo 30 pontos (se retenção for > 50%)
        $retentionScore = min(30, ($retention / 50) * 30);
        
        // CTR (Click-Through Rate ou Taxa de Clique): 
        // O que é: Mede a porcentagem de pessoas que viram a capa (thumbnail) do vídeo e decidiram clicar para assistir.
        // Para que serve: É o termômetro principal para saber se o título e a capa estão atrativos. Um CTR alto significa que o vídeo chama muita atenção (chama o clique).
        // Como funciona no código: O CTR compõe até 30% da nota final (Winner Score) do vídeo.
        // Nós dividimos o CTR atual por 10 (considerando 10% como um "CTR excelente/ideal" no YouTube). 
        // Depois multiplicamos por 30 (que é a pontuação máxima possível para esse critério). 
        // A função `min(30, ...)` serve como um teto: se o vídeo for viral e tiver 15% de CTR, ele crava nos 30 pontos e não "quebra" a nota final passando de 100.
        $ctrScore = min(30, ($ctr / 10) * 30);
        
        // Inscritos: 1 pt por inscrito até 10 pts
        $subscribersScore = min(10, $subscribers);

        $total = $viewsScore + $watchTimeScore + $retentionScore + $ctrScore + $subscribersScore;
        
        return (int) min(100, max(0, $total));
    }

    private function buildOverviewMetrics(array $current, array $previous): array
    {
        return [
            'views' => $current['views'],
            'viewsTrend' => $this->calculateTrend($current['views'], $previous['views']),
            'watchTimeHours' => round($current['estimatedMinutesWatched'] / 60, 1),
            'watchTimeTrend' => $this->calculateTrend($current['estimatedMinutesWatched'], $previous['estimatedMinutesWatched']),
            'netSubscribers' => $current['netSubscribers'],
            'netSubscribersTrend' => $this->calculateTrend($current['netSubscribers'], $previous['netSubscribers']),
            'estimatedRevenue' => round($current['estimatedRevenue'], 2),
            'revenueTrend' => $this->calculateTrend($current['estimatedRevenue'], $previous['estimatedRevenue']),
            'avgViewDuration' => $this->formatDuration($current['averageViewDuration']),
            'avgViewDurationTrend' => $this->calculateTrend($current['averageViewDuration'], $previous['averageViewDuration']),
        ];
    }

    private function calculateTrend($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function formatDuration(int $seconds): string
    {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    private function buildTimeSeriesData(array $currentSeries, array $previousSeries, string $startDate, string $endDate, string $dimension = 'day'): array
    {
        $categories = [];
        $currentData = [];
        $previousData = [];

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Generate date array based on dimension
        if ($dimension === 'month') {
            $period = \Carbon\CarbonPeriod::create($start->startOfMonth(), '1 month', $end->startOfMonth());
            $format = 'Y-m';
            $displayFormat = 'M Y';
        } else {
            $period = \Carbon\CarbonPeriod::create($start, '1 day', $end);
            $format = 'Y-m-d';
            $displayFormat = 'd M';
        }
        
        // We align previous series by index to show it alongside the current series
        $prevValues = array_values($previousSeries);
        $i = 0;

        foreach ($period as $date) {
            $dateString = $date->format($format);
            $categories[] = $date->translatedFormat($displayFormat);
            $currentData[] = $currentSeries[$dateString] ?? 0;
            $previousData[] = $prevValues[$i] ?? 0;
            $i++;
        }

        return [
            'categories' => $categories,
            'series' => [
                [
                    'name' => 'Views',
                    'data' => $currentData
                ],
                [
                    'name' => 'Views (Período Anterior)',
                    'data' => $previousData
                ]
            ]
        ];
    }

    private function buildTrafficSources(array $sources): array
    {
        // Se a API não retornou dados (ex: sem acesso ou dados zerados), enviamos fallback vazio
        if (empty($sources['labels'])) {
             return [
                'labels' => ['Pesquisa', 'Sugeridos', 'Externo', 'Outros'],
                'series' => [0, 0, 0, 0]
             ];
        }
        return $sources;
    }

    private function translateTrafficSource(string $source): string
    {
        $map = [
            'YT_SEARCH' => 'Pesquisa do YouTube',
            'RELATED_VIDEO' => 'Vídeos Sugeridos',
            'SUBSCRIBER' => 'Recursos de Navegação (Inscritos)',
            'EXT_URL' => 'Externo',
            'NO_LINK_OTHER' => 'Outros',
            'PLAYLIST' => 'Playlists'
        ];

        return $map[$source] ?? ucfirst(strtolower(str_replace('_', ' ', $source)));
    }

    private function generateAlerts(array $topVideos, array $currentOverview, array $previousOverview): array
    {
        $alerts = [];

        // Alerta de queda de visualizações
        $viewsTrend = $this->calculateTrend($currentOverview['views'], $previousOverview['views']);
        if ($viewsTrend <= -10) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Queda de Visualizações',
                'message' => "Seu canal teve uma queda de " . abs($viewsTrend) . "% nas visualizações em relação ao período anterior.",
                'icon' => 'mdi-trending-down'
            ];
        }

        // Alerta inteligente nos vídeos
        foreach ($topVideos as $video) {
            // Simulação de CTR baixo para vídeos com muitas views
            if ($video['views'] > 1000 && $video['ctr'] < 3.0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Queda de CTR Identificada',
                    'message' => "O vídeo '{$video['title']}' está com bastante entrega, mas o CTR está baixo ({$video['ctr']}%). Considere trocar a thumbnail.",
                    'icon' => 'mdi-alert'
                ];
                break; // Apenas um alerta deste tipo para não flodar
            }
        }

        if (empty($alerts)) {
             $alerts[] = [
                'type' => 'success',
                'title' => 'Tudo em ordem!',
                'message' => "As métricas do seu canal parecem saudáveis neste período.",
                'icon' => 'mdi-check-circle'
            ];
        }

        return $alerts;
    }
}
