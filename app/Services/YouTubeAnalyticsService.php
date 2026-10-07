<?php

namespace App\Services;

use App\Models\SocialAccount;
use Carbon\CarbonPeriod;
use Closure;
use DateInterval;
use Exception;
use Google\Service\YouTube;
use Google\Service\YouTubeAnalytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class YouTubeAnalyticsService
{
    public const DEFAULT_BEST_HOURS = [14, 18, 20];

    private const DASHBOARD_CACHE_HOURS = 6;

    private const BEST_HOURS_CACHE_DAYS = 7;

    // v4: o winnerScore mudou (sem CTR); a versão nova invalida os dashboards antigos em cache.
    private const DASHBOARD_CACHE_VERSION = 'v4';

    private const YOUTUBE_LAUNCH_DATE = '2005-02-14';

    private const MONTHLY_GROUPING_AFTER_DAYS = 365;

    private const TOP_VIDEOS_LIMIT = 50;

    private const TRAFFIC_SOURCES_LIMIT = 5;

    private const RECENT_UPLOADS_LIMIT = 50;

    private const BEST_HOURS_COUNT = 3;

    private const BEST_HOURS_TIMEZONE = 'America/Sao_Paulo'; // MVP: depois pode vir do usuário

    private const SHORT_MAX_SECONDS = 60;

    private const OVERVIEW_METRICS = 'views,estimatedMinutesWatched,subscribersGained,subscribersLost,averageViewDuration';

    private const TOP_VIDEO_METRICS = 'views,estimatedMinutesWatched,averageViewDuration,subscribersGained';

    // winnerScore (0 a 100): peso de cada critério e o valor a partir do qual ele pontua cheio.
    private const SCORE_WEIGHTS = ['views' => 30, 'watchTime' => 15, 'retention' => 40, 'subscribers' => 15];

    private const VIEWS_FOR_FULL_SCORE = 60;

    private const WATCH_MINUTES_FOR_FULL_SCORE = 100;

    private const RETENTION_FOR_FULL_SCORE = 40;

    private const SUBSCRIBERS_FOR_FULL_SCORE = 10;

    private const WINNER_ALERT_MIN_SCORE = 75;

    private const VIEWS_DROP_ALERT_PERCENT = -10;

    private const TRAFFIC_SOURCE_LABELS = [
        'YT_SEARCH' => 'Pesquisa do YouTube',
        'RELATED_VIDEO' => 'Vídeos Sugeridos',
        'SUBSCRIBER' => 'Recursos de Navegação (Inscritos)',
        'EXT_URL' => 'Externo',
        'NO_LINK_OTHER' => 'Outros',
        'PLAYLIST' => 'Playlists',
    ];

    private const EMPTY_TRAFFIC_SOURCES = [
        'labels' => ['Pesquisa', 'Sugeridos', 'Externo', 'Outros'],
        'series' => [0, 0, 0, 0],
    ];

    private const EMPTY_OVERVIEW = [
        'views' => 0,
        'estimatedMinutesWatched' => 0,
        'netSubscribers' => 0,
        'estimatedRevenue' => 0.0,
        'averageViewDuration' => 0,
    ];

    public function __construct(
        private readonly YouTubeService $youTubeService,
    ) {}

    /**
     * Dashboard do canal no período pedido, comparado com o período anterior de mesmo tamanho.
     * Fica em cache para poupar a cota da API do Google.
     */
    public function getDashboardData(SocialAccount $account, string $dateRange, ?string $customStart = null, ?string $customEnd = null, bool $forceRefresh = false): array
    {
        [$current, $previous, $dimension] = $this->resolvePeriods($dateRange, $customStart, $customEnd);

        $cacheKey = 'youtube_analytics_'.self::DASHBOARD_CACHE_VERSION."_{$account->id}_{$current['start']}_{$current['end']}_{$dimension}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            now()->addHours(self::DASHBOARD_CACHE_HOURS),
            fn () => $this->buildDashboard($account, $current, $previous, $dimension),
        );
    }

    /**
     * As horas (fuso de São Paulo) em que os últimos vídeos do canal tiveram mais views, em média.
     * O fallback de erro não vai para o cache: uma falha temporária não pode esconder os horários reais por dias.
     */
    public function getBestPublishHours(SocialAccount $account): array
    {
        $cacheKey = "youtube_best_hours_{$account->id}";

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $bestHours = $this->calculateBestHours(new YouTube($this->youTubeService->clientFor($account)));
        } catch (Exception $e) {
            Log::error("Failed to get best publish hours: {$e->getMessage()}");

            return self::DEFAULT_BEST_HOURS;
        }

        Cache::put($cacheKey, $bestHours, now()->addDays(self::BEST_HOURS_CACHE_DAYS));

        return $bestHours;
    }

    // ---------------------------------------------------------------------
    // Períodos
    // ---------------------------------------------------------------------

    /**
     * @return array{0: array{start: string, end: string}, 1: array{start: string, end: string}, 2: string}
     */
    private function resolvePeriods(string $dateRange, ?string $customStart, ?string $customEnd): array
    {
        [$start, $end, $previousStart, $previousEnd] = $customStart && $customEnd
            ? $this->customPeriods($customStart, $customEnd)
            : $this->presetPeriods($dateRange);

        // Períodos longos agrupam por mês para o gráfico não ter pontos demais.
        $dimension = $start->diffInDays($end) > self::MONTHLY_GROUPING_AFTER_DAYS ? 'month' : 'day';

        if ($dimension === 'month') {
            $start->startOfMonth();
            $end->endOfMonth();
            $previousStart->startOfMonth();
            $previousEnd->endOfMonth();
        }

        return [
            ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')],
            ['start' => $previousStart->format('Y-m-d'), 'end' => $previousEnd->format('Y-m-d')],
            $dimension,
        ];
    }

    /**
     * O período anterior tem o mesmo tamanho e termina um dia antes do início do atual.
     */
    private function customPeriods(string $customStart, string $customEnd): array
    {
        $start = Carbon::parse($customStart)->startOfDay();
        $end = Carbon::parse($customEnd)->endOfDay();
        $previousEnd = $start->copy()->subDay()->endOfDay();

        return [$start, $end, $previousEnd->copy()->subDays($start->diffInDays($end))->startOfDay(), $previousEnd];
    }

    private function presetPeriods(string $dateRange): array
    {
        $now = now();

        return match ($dateRange) {
            // Sem período anterior equivalente: compara com ele mesmo, o que zera as tendências.
            'lifetime' => [Carbon::parse(self::YOUTUBE_LAUNCH_DATE), $now->copy(), Carbon::parse(self::YOUTUBE_LAUNCH_DATE), $now->copy()],
            'last_28_days' => [$now->copy()->subDays(28), $now->copy(), $now->copy()->subDays(56), $now->copy()->subDays(28)],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy(), $now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth(),
                $now->copy()->subMonths(2)->startOfMonth(), $now->copy()->subMonths(2)->endOfMonth(),
            ],
            default => [$now->copy()->subDays(7), $now->copy(), $now->copy()->subDays(14), $now->copy()->subDays(7)], // last_7_days
        };
    }

    // ---------------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------------

    private function buildDashboard(SocialAccount $account, array $current, array $previous, string $dimension): array
    {
        $client = $this->youTubeService->clientFor($account);
        $analytics = new YouTubeAnalytics($client);

        $currentOverview = $this->fetchOverview($analytics, $current);
        $previousOverview = $this->fetchOverview($analytics, $previous);
        $topVideos = $this->fetchTopVideos($analytics, new YouTube($client), $current);

        return [
            'overviewMetrics' => $this->buildOverviewMetrics($currentOverview, $previousOverview),
            'timeSeriesData' => $this->buildTimeSeriesData(
                $this->fetchTimeSeries($analytics, $current, $dimension),
                $this->fetchTimeSeries($analytics, $previous, $dimension),
                $current,
                $dimension,
            ),
            'trafficSources' => $this->fetchTrafficSources($analytics, $current),
            'topVideos' => $topVideos,
            'alerts' => $this->generateAlerts($topVideos, $currentOverview, $previousOverview),
            'channelScore' => $this->calculateChannelScore($topVideos, $account),
        ];
    }

    private function report(YouTubeAnalytics $analytics, array $period, string $metrics, array $options = []): array
    {
        return $analytics->reports->query([
            'ids' => 'channel==MINE',
            'startDate' => $period['start'],
            'endDate' => $period['end'],
            'metrics' => $metrics,
            ...$options,
        ])->getRows() ?? [];
    }

    /**
     * Consulta um bloco do dashboard. Erro de autorização sobe (o controller pede nova autorização e nada vai
     * para o cache); os demais erros viram o valor vazio do bloco.
     */
    private function fetchOrEmpty(string $block, Closure $fetch, mixed $empty): mixed
    {
        try {
            return $fetch();
        } catch (Exception $e) {
            Log::error("YouTube Analytics API Error ({$block}): {$e->getMessage()}");

            if ($this->isAuthorizationError($e)) {
                throw $e;
            }

            return $empty;
        }
    }

    private function isAuthorizationError(Exception $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '401')
            || str_contains($message, '403')
            || str_contains($message, 'disabled')
            || stripos($message, 'permission') !== false;
    }

    /**
     * Tenta com receita; canais não monetizados recusam a métrica, então repete sem ela.
     */
    private function fetchOverview(YouTubeAnalytics $analytics, array $period): array
    {
        return $this->fetchOrEmpty('Overview', function () use ($analytics, $period) {
            try {
                $row = $this->report($analytics, $period, self::OVERVIEW_METRICS.',estimatedRevenue')[0] ?? null;
            } catch (Exception) {
                $row = $this->report($analytics, $period, self::OVERVIEW_METRICS)[0] ?? null;
            }

            if (! $row) {
                return self::EMPTY_OVERVIEW;
            }

            return [
                'views' => (int) $row[0],
                'estimatedMinutesWatched' => (int) $row[1],
                'netSubscribers' => (int) $row[2] - (int) $row[3],
                'averageViewDuration' => (int) $row[4],
                'estimatedRevenue' => (float) ($row[5] ?? 0.0),
            ];
        }, self::EMPTY_OVERVIEW);
    }

    /**
     * @return array<string, array{views: int, watchTime: int}> indexado pela data (Y-m-d ou Y-m)
     */
    private function fetchTimeSeries(YouTubeAnalytics $analytics, array $period, string $dimension): array
    {
        $rows = $this->fetchOrEmpty('TimeSeries', fn () => $this->report(
            $analytics,
            $period,
            'views,estimatedMinutesWatched',
            ['dimensions' => $dimension, 'sort' => $dimension],
        ), []);

        return collect($rows)
            ->mapWithKeys(fn (array $row) => [$row[0] => ['views' => (int) $row[1], 'watchTime' => (int) $row[2]]])
            ->all();
    }

    private function fetchTrafficSources(YouTubeAnalytics $analytics, array $period): array
    {
        $rows = $this->fetchOrEmpty('Traffic Sources', fn () => $this->report($analytics, $period, 'views', [
            'dimensions' => 'insightTrafficSourceType',
            'sort' => '-views',
            'maxResults' => self::TRAFFIC_SOURCES_LIMIT,
        ]), []);

        if ($rows === []) {
            return self::EMPTY_TRAFFIC_SOURCES;
        }

        return [
            'labels' => array_map(fn (array $row) => $this->trafficSourceLabel($row[0]), $rows),
            'series' => array_map(fn (array $row) => (int) $row[1], $rows),
        ];
    }

    private function trafficSourceLabel(string $source): string
    {
        return self::TRAFFIC_SOURCE_LABELS[$source] ?? ucfirst(strtolower(str_replace('_', ' ', $source)));
    }

    // ---------------------------------------------------------------------
    // Top vídeos
    // ---------------------------------------------------------------------

    /**
     * Os vídeos com mais views no período. Título, capa, duração e totais vêm da Data API;
     * se ela falhar, o vídeo aparece só com os dados do Analytics.
     */
    private function fetchTopVideos(YouTubeAnalytics $analytics, YouTube $youtube, array $period): array
    {
        try {
            $rows = $this->report($analytics, $period, self::TOP_VIDEO_METRICS, [
                'dimensions' => 'video',
                'sort' => '-views',
                'maxResults' => self::TOP_VIDEOS_LIMIT,
            ]);
        } catch (Exception $e) {
            Log::error("YouTube Analytics API Error (Top Videos): {$e->getMessage()}");

            return [];
        }

        $details = $this->fetchVideoDetails($youtube, array_column($rows, 0));

        return array_map(fn (array $row) => $this->buildTopVideo($row, $details[$row[0]] ?? null), $rows);
    }

    /**
     * @return array<string, YouTube\Video> indexado pelo id do vídeo
     */
    private function fetchVideoDetails(YouTube $youtube, array $videoIds): array
    {
        if ($videoIds === []) {
            return [];
        }

        try {
            $videos = $youtube->videos->listVideos('snippet,contentDetails,statistics', ['id' => implode(',', $videoIds)]);

            return collect($videos->getItems())->keyBy(fn (YouTube\Video $video) => $video->getId())->all();
        } catch (Exception $e) {
            Log::warning("YouTube Data API Error (Snippets): {$e->getMessage()}");

            return [];
        }
    }

    private function buildTopVideo(array $row, ?YouTube\Video $details): array
    {
        $videoId = $row[0];
        [$views, $watchTimeMinutes, $avgViewSeconds, $subscribersGained] = array_map('intval', array_slice($row, 1, 4));

        $video = [
            'id' => $videoId,
            'title' => "Vídeo ID: {$videoId}",
            'thumbnail' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
            'views' => $views,
            'watchTime' => $watchTimeMinutes,
            // A API não oferece CTR de thumbnail (a métrica antiga media anotações, removidas do YouTube em 2019).
            'ctr' => 0.0,
            'retention' => 0,
            'winnerScore' => 0,
            'is_short' => false,
            'totalViews' => 0,
            'totalLikes' => 0,
            'totalComments' => 0,
        ];

        if (! $details) {
            return $video;
        }

        $thumbnails = $details->getSnippet()->getThumbnails();
        $statistics = $details->getStatistics();
        [$retention, $isShort] = $this->retentionAndFormat($details->getContentDetails()->getDuration(), $avgViewSeconds, $watchTimeMinutes);

        return [
            ...$video,
            'title' => $details->getSnippet()->getTitle(),
            'thumbnail' => ($thumbnails->getHigh() ?? $thumbnails->getDefault())->getUrl(),
            'retention' => $retention,
            'winnerScore' => $this->calculateWinnerScore($views, $watchTimeMinutes, $retention, $subscribersGained),
            'is_short' => $isShort,
            'totalViews' => (int) $statistics->getViewCount(),
            'totalLikes' => (int) $statistics->getLikeCount(),
            'totalComments' => (int) $statistics->getCommentCount(),
        ];
    }

    /**
     * Retenção = duração média assistida ÷ duração do vídeo. Sem duração legível, estima pelo tempo assistido.
     *
     * @return array{0: float|int, 1: bool} [retenção em %, é Short]
     */
    private function retentionAndFormat(string $isoDuration, int $avgViewSeconds, int $watchTimeMinutes): array
    {
        try {
            $duration = new DateInterval($isoDuration);
        } catch (Exception) {
            $retention = round(min(100, $avgViewSeconds / max(1, $watchTimeMinutes * 60) * 100), 1);

            return [$retention, $avgViewSeconds <= self::SHORT_MAX_SECONDS];
        }

        $durationSeconds = $duration->d * 86400 + $duration->h * 3600 + $duration->i * 60 + $duration->s;

        if ($durationSeconds <= 0) {
            return [0, false];
        }

        return [round(min(100, $avgViewSeconds / $durationSeconds * 100), 1), $durationSeconds <= self::SHORT_MAX_SECONDS];
    }

    /**
     * Nota de 0 a 100: cada critério vira uma fração de 0 a 1 do seu peso.
     * Views e tempo assistido crescem em escala logarítmica, para canais pequenos também pontuarem.
     */
    private function calculateWinnerScore(int $views, int $watchTimeMinutes, float $retention, int $subscribersGained): int
    {
        $fractions = [
            'views' => log($views + 1) / log(self::VIEWS_FOR_FULL_SCORE + 1),
            'watchTime' => log($watchTimeMinutes + 1) / log(self::WATCH_MINUTES_FOR_FULL_SCORE + 1),
            'retention' => $retention / self::RETENTION_FOR_FULL_SCORE,
            'subscribers' => $subscribersGained / self::SUBSCRIBERS_FOR_FULL_SCORE,
        ];

        $score = collect($fractions)
            ->map(fn (float $fraction, string $criterion) => max(0, min(1, $fraction)) * self::SCORE_WEIGHTS[$criterion])
            ->sum();

        return (int) min(100, $score);
    }

    // ---------------------------------------------------------------------
    // Visão geral, gráfico, alertas e nota do canal
    // ---------------------------------------------------------------------

    private function buildOverviewMetrics(array $current, array $previous): array
    {
        return [
            'views' => $current['views'],
            'viewsTrend' => $this->trend($current['views'], $previous['views']),
            'watchTimeHours' => round($current['estimatedMinutesWatched'] / 60, 1),
            'watchTimeTrend' => $this->trend($current['estimatedMinutesWatched'], $previous['estimatedMinutesWatched']),
            'netSubscribers' => $current['netSubscribers'],
            'netSubscribersTrend' => $this->trend($current['netSubscribers'], $previous['netSubscribers']),
            'estimatedRevenue' => round($current['estimatedRevenue'], 2),
            'revenueTrend' => $this->trend($current['estimatedRevenue'], $previous['estimatedRevenue']),
            'avgViewDuration' => sprintf('%d:%02d', intdiv($current['averageViewDuration'], 60), $current['averageViewDuration'] % 60),
            'avgViewDurationTrend' => $this->trend($current['averageViewDuration'], $previous['averageViewDuration']),
        ];
    }

    /**
     * Variação percentual em relação ao período anterior.
     */
    private function trend(int|float $current, int|float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    /**
     * Série atual e anterior lado a lado; a anterior é alinhada pela posição, não pela data.
     */
    private function buildTimeSeriesData(array $currentSeries, array $previousSeries, array $period, string $dimension): array
    {
        [$step, $keyFormat, $labelFormat] = $dimension === 'month' ? ['1 month', 'Y-m', 'M Y'] : ['1 day', 'Y-m-d', 'd M'];
        $start = Carbon::parse($period['start']);
        $end = Carbon::parse($period['end']);

        if ($dimension === 'month') {
            $start->startOfMonth();
            $end->startOfMonth();
        }

        $empty = ['views' => 0, 'watchTime' => 0];
        $previousValues = array_values($previousSeries);

        $points = collect(CarbonPeriod::create($start, $step, $end))->values()->map(fn ($date, int $index) => [
            'label' => $date->translatedFormat($labelFormat),
            'current' => $currentSeries[$date->format($keyFormat)] ?? $empty,
            'previous' => $previousValues[$index] ?? $empty,
        ]);

        $hours = fn (string $series) => $points->map(fn (array $point) => round($point[$series]['watchTime'] / 60, 2))->all();

        return [
            'categories' => $points->pluck('label')->all(),
            'series' => [
                'views' => [
                    ['name' => 'Views', 'data' => $points->pluck('current.views')->all()],
                    ['name' => 'Views (Período Anterior)', 'data' => $points->pluck('previous.views')->all()],
                ],
                'watchTime' => [
                    ['name' => 'Tempo de Exibição (h)', 'data' => $hours('current')],
                    ['name' => 'Tempo de Exibição (Período Anterior)', 'data' => $hours('previous')],
                ],
            ],
        ];
    }

    private function generateAlerts(array $topVideos, array $currentOverview, array $previousOverview): array
    {
        $alerts = [];
        $bestVideo = collect($topVideos)->sortByDesc('winnerScore')->first();

        if ($bestVideo && $bestVideo['winnerScore'] > self::WINNER_ALERT_MIN_SCORE) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Vídeo Vencedor Identificado',
                'message' => "O vídeo '{$bestVideo['title']}' está com ótima performance e atingiu um Score de {$bestVideo['winnerScore']}. Faça mais conteúdo similar.",
                'icon' => 'mdi-trophy',
            ];
        }

        $viewsTrend = $this->trend($currentOverview['views'], $previousOverview['views']);

        if ($viewsTrend <= self::VIEWS_DROP_ALERT_PERCENT) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Alerta de Retenção',
                'message' => 'A média de visualização caiu consideravelmente ('.abs($viewsTrend).'%) nos vídeos esta semana.',
                'icon' => 'mdi-trending-down',
            ];
        }

        return $alerts ?: [[
            'type' => 'info',
            'title' => 'Tudo em ordem!',
            'message' => 'As métricas do seu canal parecem saudáveis neste período.',
            'icon' => 'mdi-check-circle',
        ]];
    }

    /**
     * Nota do canal (0 a 100): desempenho dos vídeos do período (até 70) + frequência de postagem (até 30).
     */
    private function calculateChannelScore(array $topVideos, SocialAccount $account): int
    {
        $averageVideoScore = collect($topVideos)->avg('winnerScore') ?? 0;
        $contentScore = $averageVideoScore > 0 ? 20 + $averageVideoScore * 0.5 : 0;

        $daysSinceLastPost = $account->daysSinceLastActivity();
        $frequencyScore = match (true) {
            $daysSinceLastPost === null => 0,
            $daysSinceLastPost <= 3 => 30,
            $daysSinceLastPost <= 7 => 20,
            $daysSinceLastPost <= 15 => 10,
            default => 0,
        };

        return (int) min(100, $contentScore + $frequencyScore);
    }

    // ---------------------------------------------------------------------
    // Melhores horários
    // ---------------------------------------------------------------------

    private function calculateBestHours(YouTube $youtube): array
    {
        $channel = $youtube->channels->listChannels('contentDetails', ['mine' => true])->getItems()[0] ?? null;

        if (! $channel) {
            return self::DEFAULT_BEST_HOURS;
        }

        $uploads = $youtube->playlistItems->listPlaylistItems('snippet', [
            'playlistId' => $channel->getContentDetails()->getRelatedPlaylists()->getUploads(),
            'maxResults' => self::RECENT_UPLOADS_LIMIT,
        ])->getItems();

        $videoIds = array_map(fn ($item) => $item->getSnippet()->getResourceId()->getVideoId(), $uploads);

        if ($videoIds === []) {
            return self::DEFAULT_BEST_HOURS;
        }

        $videos = $youtube->videos->listVideos('snippet,statistics', ['id' => implode(',', $videoIds)])->getItems();

        $bestHours = collect($videos)
            ->groupBy(fn (YouTube\Video $video) => (int) Carbon::parse($video->getSnippet()->getPublishedAt())->setTimezone(self::BEST_HOURS_TIMEZONE)->format('G'))
            ->map(fn ($hourVideos) => $hourVideos->avg(fn (YouTube\Video $video) => (int) $video->getStatistics()->getViewCount()))
            ->sortDesc()
            ->keys()
            ->take(self::BEST_HOURS_COUNT)
            ->all();

        return $bestHours ?: self::DEFAULT_BEST_HOURS;
    }
}
