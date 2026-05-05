<?php

namespace App\Services;

use App\Models\SocialAccount;
use Google\Client;
use Google\Service\YouTubeAnalytics;
use Google\Service\YouTube;
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
    public function getDashboardData(SocialAccount $account, string $dateRange, ?string $customStart = null, ?string $customEnd = null, bool $forceRefresh = false): array
    {
        // 1. Determine date ranges
        $dates = $this->calculateDateRange($dateRange, $customStart, $customEnd);
        // Determine dimension based on date range (use months if > 365 days to avoid too many points)
        $diffDays = $dates['current']['start']->diffInDays($dates['current']['end']);
        $dimension = $diffDays > 365 ? 'month' : 'day';

        if ($dimension === 'month') {
            $dates['current']['start'] = $dates['current']['start']->startOfMonth();
            $dates['current']['end'] = $dates['current']['end']->endOfMonth();
            $dates['previous']['start'] = $dates['previous']['start']->startOfMonth();
            $dates['previous']['end'] = $dates['previous']['end']->endOfMonth();
        }

        $startDate = $dates['current']['start']->format('Y-m-d');
        $endDate = $dates['current']['end']->format('Y-m-d');
        
        $prevStartDate = $dates['previous']['start']->format('Y-m-d');
        $prevEndDate = $dates['previous']['end']->format('Y-m-d');

        // Cache key based on account and dates (updated to v3 to bypass previous empty cache)
        $cacheKey = "youtube_analytics_v3_{$account->id}_{$startDate}_{$endDate}_{$dimension}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

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
            $topVideos = $this->fetchTopVideos($service, $client, $startDate, $endDate);

            // Fetch traffic sources
            $trafficSources = $this->fetchTrafficSources($service, $startDate, $endDate);

            return [
                'overviewMetrics' => $this->buildOverviewMetrics($currentOverview, $previousOverview),
                'timeSeriesData' => $this->buildTimeSeriesData($currentSeries, $previousSeries, $startDate, $endDate, $dimension),
                'trafficSources' => $this->buildTrafficSources($trafficSources),
                'topVideos' => $topVideos,
                'alerts' => $this->generateAlerts($topVideos, $currentOverview, $previousOverview),
                'channelScore' => $this->calculateChannelScore($topVideos, $account),
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

    /**
     * Calcula os melhores horários para postar com base no histórico do canal.
     * Analisa os últimos 50 vídeos e identifica quais horários geraram mais views em média.
     */
    public function getBestPublishHours(SocialAccount $account): array
    {
        $cacheKey = "youtube_best_hours_{$account->id}";
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($account) {
            try {
                $client = $this->getAuthenticatedClient($account);
                $youtubeAPI = new YouTube($client);

                // 1. Pega a playlist de uploads do canal
                $channelsResponse = $youtubeAPI->channels->listChannels('contentDetails', ['mine' => true]);
                if (empty($channelsResponse->getItems())) {
                    return [14, 18, 20]; // Fallback
                }
                
                $uploadsPlaylistId = $channelsResponse->getItems()[0]->getContentDetails()->getRelatedPlaylists()->getUploads();

                // 2. Pega os últimos 50 vídeos
                $playlistResponse = $youtubeAPI->playlistItems->listPlaylistItems('snippet', [
                    'playlistId' => $uploadsPlaylistId,
                    'maxResults' => 50
                ]);

                $videoIds = [];
                foreach ($playlistResponse->getItems() as $item) {
                    $videoIds[] = $item->getSnippet()->getResourceId()->getVideoId();
                }

                if (empty($videoIds)) {
                    return [14, 18, 20]; // Fallback
                }

                // 3. Pega as estatísticas (views) e snippet (publishedAt) desses vídeos
                $videosResponse = $youtubeAPI->videos->listVideos('snippet,statistics', [
                    'id' => implode(',', $videoIds)
                ]);

                $hourlyPerformance = [];

                foreach ($videosResponse->getItems() as $video) {
                    $publishedAt = $video->getSnippet()->getPublishedAt(); 
                    $views = (int) $video->getStatistics()->getViewCount();
                    
                    //NOTE Converte para o fuso horário de Brasília para MVP (depois pode pegar timezone do User)
                    $hour = Carbon::parse($publishedAt)->setTimezone('America/Sao_Paulo')->format('H');
                    
                    if (!isset($hourlyPerformance[$hour])) {
                        $hourlyPerformance[$hour] = ['views' => 0, 'count' => 0];
                    }
                    
                    $hourlyPerformance[$hour]['views'] += $views;
                    $hourlyPerformance[$hour]['count']++;
                }

                $hourAverages = [];
                foreach ($hourlyPerformance as $hour => $data) {
                    $hourAverages[$hour] = $data['views'] / $data['count'];
                }

                // Ordena pelas horas com maior média de views
                arsort($hourAverages);

                $bestHours = array_keys(array_slice($hourAverages, 0, 3, true));
                
                $bestHours = array_map('intval', $bestHours);

                // Se não tiver dados, fallback
                if (empty($bestHours)) {
                    return [14, 18, 20];
                }

                return $bestHours;

            } catch (Exception $e) {
                Log::error("Failed to get best publish hours: " . $e->getMessage());
                return [14, 18, 20]; // Fallback padrão
            }
        });
    }

    private function calculateDateRange(string $dateRange, ?string $customStart = null, ?string $customEnd = null): array
    {
        $now = Carbon::now();

        if ($customStart && $customEnd) {
            $start = Carbon::parse($customStart)->startOfDay();
            $end = Carbon::parse($customEnd)->endOfDay();
            
            $diffInDays = $start->diffInDays($end);
            
            // Período anterior do mesmo tamanho, terminando um dia antes do start atual
            $prevEnd = $start->copy()->subDay()->endOfDay();
            $prevStart = $prevEnd->copy()->subDays($diffInDays)->startOfDay();
            
            return [
                'current' => ['start' => $start, 'end' => $end],
                'previous' => ['start' => $prevStart, 'end' => $prevEnd],
            ];
        }
        
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
            $metricsWithRevenue = 'views,estimatedMinutesWatched,subscribersGained,subscribersLost,averageViewDuration,estimatedRevenue';
            $metricsWithoutRevenue = 'views,estimatedMinutesWatched,subscribersGained,subscribersLost,averageViewDuration';

            $hasRevenue = false;
            try {
                $response = $service->reports->query([
                    'ids' => "channel==MINE",
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'metrics' => $metricsWithRevenue,
                ]);
                $hasRevenue = true;
            } catch (Exception $e) {
                // Fallback para canais não monetizados
                $response = $service->reports->query([
                    'ids' => "channel==MINE",
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'metrics' => $metricsWithoutRevenue,
                ]);
            }
            
            if (empty($response->getRows())) {
                return $this->emptyOverview();
            }

            $row = $response->getRows()[0];
            return [
                'views' => (int) $row[0],
                'estimatedMinutesWatched' => (int) $row[1],
                'netSubscribers' => (int) $row[2] - (int) $row[3],
                'averageViewDuration' => (int) $row[4],
                'estimatedRevenue' => $hasRevenue ? (float) ($row[5] ?? 0.0) : 0.0,
            ];
        } catch (Exception $e) {
            Log::error('YouTube Analytics API Error (Overview): ' . $e->getMessage());
            // Se for erro de permissão ou API desativada, não podemos engolir o erro, 
            // senão ele salva "0 views" no cache por 6 horas.
            if (str_contains($e->getMessage(), 'disabled') || str_contains($e->getMessage(), '403') || stripos($e->getMessage(), 'permission') !== false || str_contains($e->getMessage(), '401')) {
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
                'metrics' => 'views,estimatedMinutesWatched',
                'dimensions' => $dimension,
                'sort' => $dimension,
            ];

            $response = $service->reports->query($optParams);
            
            $data = [];
            if ($response->getRows()) {
                foreach ($response->getRows() as $row) {
                    $data[$row[0]] = [
                        'views' => (int) $row[1],
                        'watchTime' => (int) $row[2],
                    ];
                }
            }
            return $data;
        } catch (Exception $e) {
            Log::error('YouTube Analytics API Error (TimeSeries): ' . $e->getMessage());
            if (str_contains($e->getMessage(), 'disabled') || str_contains($e->getMessage(), '403') || stripos($e->getMessage(), 'permission') !== false || str_contains($e->getMessage(), '401')) {
                throw $e;
            }
            return [];
        }
    }

    private function fetchTopVideos(YouTubeAnalytics $service, Client $client, string $startDate, string $endDate): array
    {
        try {
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
            
            $videosData = [];
            $videoIds = [];
            if ($response->getRows()) {
                foreach ($response->getRows() as $row) {
                    $videoId = $row[0];
                    $videoIds[] = $videoId;
                    $views = (int) $row[1];
                    $watchTimeMinutes = (int) $row[2];
                    $avgViewDurationSeconds = (int) $row[3];
                    $subscribersGained = (int) $row[4];
                    $ctr = (float) $row[5]; 

                    // Placeholder de retenção até calcularmos com o ISO8601
                    $retention = 0; 
                    $ctrPercent = round($ctr * 100, 1);

                    $videosData[$videoId] = [
                        'id' => $videoId,
                        'title' => "Vídeo ID: {$videoId}", 
                        'thumbnail' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
                        'views' => $views,
                        'watchTime' => $watchTimeMinutes,
                        'ctr' => $ctrPercent,
                        'retention' => $retention,
                        'winnerScore' => 0,
                        'avgViewDurationSeconds' => $avgViewDurationSeconds,
                        'subscribersGained' => $subscribersGained,
                        'totalViews' => 0,
                        'totalLikes' => 0,
                        'totalComments' => 0
                    ];
                }
            }

            // Integrar com YouTube Data API para pegar Títulos, Thumbnails e Duração real
            if (!empty($videoIds)) {
                try {
                    $youtubeAPI = new YouTube($client);
                    $snippetResponse = $youtubeAPI->videos->listVideos('snippet,contentDetails,statistics', [
                        'id' => implode(',', $videoIds)
                    ]);
                    
                    foreach ($snippetResponse->getItems() as $videoItem) {
                        $id = $videoItem->getId();
                        $snippet = $videoItem->getSnippet();
                        $durationIso = $videoItem->getContentDetails()->getDuration();
                        
                        if (isset($videosData[$id])) {
                            $videosData[$id]['title'] = $snippet->getTitle();
                            $thumbnails = $snippet->getThumbnails();
                            $url = $thumbnails->getHigh() ? $thumbnails->getHigh()->getUrl() : $thumbnails->getDefault()->getUrl();
                            $videosData[$id]['thumbnail'] = $url;
                            
                            $stats = $videoItem->getStatistics();
                            $videosData[$id]['totalViews'] = (int) $stats->getViewCount();
                            $videosData[$id]['totalLikes'] = (int) $stats->getLikeCount();
                            $videosData[$id]['totalComments'] = (int) $stats->getCommentCount();
                            
                            // Calcula retenção exata
                            try {
                                $interval = new \DateInterval($durationIso);
                                $totalSeconds = ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
                                
                                if ($totalSeconds > 0) {
                                    $avgSeconds = $videosData[$id]['avgViewDurationSeconds'];
                                    $realRetention = min(100, ($avgSeconds / $totalSeconds) * 100);
                                    $videosData[$id]['retention'] = round($realRetention, 1);
                                }
                            } catch (Exception $ex) {
                                // Retenção aproximada fallback
                                $watchTimeMins = $videosData[$id]['watchTime'];
                                $avgSeconds = $videosData[$id]['avgViewDurationSeconds'];
                                $videosData[$id]['retention'] = round(min(100, ($avgSeconds / max(1, $watchTimeMins * 60)) * 100), 1);
                            }

                            // Calcula Winner Score final
                            $videosData[$id]['winnerScore'] = $this->calculateWinnerScore(
                                $videosData[$id]['views'], 
                                $videosData[$id]['watchTime'], 
                                $videosData[$id]['retention'], 
                                $videosData[$id]['subscribersGained'], 
                                $videosData[$id]['ctr'] / 100 // calculateWinnerScore espera a taxa e não o percentual
                            );
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('YouTube Data API Error (Snippets): ' . $e->getMessage());
                }
            }

            // Cleanup
            foreach ($videosData as &$vid) {
                unset($vid['avgViewDurationSeconds'], $vid['subscribersGained']);
            }
            
            return array_values($videosData);
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
            if (str_contains($e->getMessage(), 'disabled') || str_contains($e->getMessage(), '403') || stripos($e->getMessage(), 'permission') !== false || str_contains($e->getMessage(), '401')) {
                throw $e;
            }
            return ['labels' => [], 'series' => []];
        }
    }

    private function calculateWinnerScore(int $views, int $watchTime, float $retention, int $subscribers, float $ctr): int
    {
        // Ponderações (exemplo MVP): Retenção (30%), CTR (30%), Views (20%), WatchTime (10%), Inscritos (10%)
        // Como views e watchtime não tem limite máximo, normalizamos com logaritmo ou thresholds.
        
        $viewsScore = min(20, log($views + 1, 1.5) * 2); // Mais sensível a poucas views
        $watchTimeScore = min(10, log($watchTime + 1, 2) * 1.5);
        
        // Retenção: máximo 30 pontos (se retenção for > 40%)
        $retentionScore = min(30, ($retention / 40) * 30);
        
        $ctrScore = min(30, ($ctr / 8) * 30); // 8% é um CTR ótimo para canais dark
        
        // Inscritos: 1 pt por inscrito até 10 pts
        $subscribersScore = min(10, $subscribers);

        $total = $viewsScore + $watchTimeScore + $retentionScore + $ctrScore + $subscribersScore;
        
        return (int) min(100, max(0, $total));
    }

    private function calculateChannelScore(array $topVideos, SocialAccount $account): int
    {
        // A nota do canal tem duas partes:
        // 1. Performance de conteúdo (80% da nota) - Média do winner score dos vídeos do período
        // 2. Frequência/Consistência (20% da nota) - Baseado em há quantos dias foi a última atividade
        
        // 1. Performance (0 a 70 pts)
        $avgVideoScore = empty($topVideos) ? 0 : collect($topVideos)->avg('winnerScore');
        $contentScore = ($avgVideoScore > 0) ? 20 + ($avgVideoScore * 0.5) : 0; // Bônus base de 20 se tiver conteúdo
        
        // 2. Frequência (0 a 20 pts)
        $frequencyScore = 0;
        $daysSince = $account->daysSinceLastActivity();
        
        if ($daysSince !== null) {
            if ($daysSince <= 3) {
                $frequencyScore = 30; // Ótima frequência
            } elseif ($daysSince <= 7) {
                $frequencyScore = 20; 
            } elseif ($daysSince <= 15) {
                $frequencyScore = 10;
            } else {
                $frequencyScore = 0;
            }
        }
        
        $finalScore = $contentScore + $frequencyScore;
        return (int) min(100, max(0, $finalScore));
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
        $currentViews = [];
        $previousViews = [];
        $currentWatchTime = [];
        $previousWatchTime = [];

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
            
            $currentData = $currentSeries[$dateString] ?? ['views' => 0, 'watchTime' => 0];
            $prevData = $prevValues[$i] ?? ['views' => 0, 'watchTime' => 0];

            $currentViews[] = $currentData['views'];
            $previousViews[] = $prevData['views'];
            
            // Convertendo watch time para horas
            $currentWatchTime[] = round($currentData['watchTime'] / 60, 2);
            $previousWatchTime[] = round($prevData['watchTime'] / 60, 2);
            
            $i++;
        }

        return [
            'categories' => $categories,
            'series' => [
                'views' => [
                    [
                        'name' => 'Views',
                        'data' => $currentViews
                    ],
                    [
                        'name' => 'Views (Período Anterior)',
                        'data' => $previousViews
                    ]
                ],
                'watchTime' => [
                    [
                        'name' => 'Tempo de Exibição (h)',
                        'data' => $currentWatchTime
                    ],
                    [
                        'name' => 'Tempo de Exibição (Período Anterior)',
                        'data' => $previousWatchTime
                    ]
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

        // 1. Queda de CTR
        foreach ($topVideos as $video) {
            if ($video['views'] > 500 && $video['ctr'] < 5.0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Queda de CTR',
                    'message' => "O vídeo '{$video['title']}' tem altas impressões mas o CTR caiu para {$video['ctr']}% nas últimas 24h. Considere trocar a thumbnail.",
                    'icon' => 'mdi-alert'
                ];
                break; 
            }
        }

        // 2. Vídeo Vencedor Identificado
        $bestVideo = null;
        $highestScore = 0;
        foreach ($topVideos as $video) {
            if ($video['winnerScore'] > $highestScore) {
                $highestScore = $video['winnerScore'];
                $bestVideo = $video;
            }
        }
        
        if ($bestVideo && $highestScore > 75) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Vídeo Vencedor Identificado',
                'message' => "O vídeo '{$bestVideo['title']}' está com ótima performance e atingiu um Score de {$highestScore}. Faça mais conteúdo similar.",
                'icon' => 'mdi-trophy'
            ];
        }

        // 3. Alerta de Retenção / Visualizações
        $viewsTrend = $this->calculateTrend($currentOverview['views'], $previousOverview['views']);
        if ($viewsTrend <= -10) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Alerta de Retenção',
                'message' => "A média de visualização caiu consideravelmente (" . abs($viewsTrend) . "%) nos vídeos esta semana.",
                'icon' => 'mdi-trending-down'
            ];
        }

        if (empty($alerts)) {
             $alerts[] = [
                'type' => 'info',
                'title' => 'Tudo em ordem!',
                'message' => "As métricas do seu canal parecem saudáveis neste período.",
                'icon' => 'mdi-check-circle'
            ];
        }

        return $alerts;
    }
}
