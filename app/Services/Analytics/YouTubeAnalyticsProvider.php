<?php

namespace App\Services\Analytics;

use App\Contracts\AnalyticsProviderInterface;
use App\Enums\Platform;
use App\Exceptions\AnalyticsException;
use App\Models\SocialAccount;
use App\Support\Analytics\AnalyticsPeriod;
use App\Support\Analytics\MetricsCalculator;
use Carbon\CarbonImmutable;
use DateInterval;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * YouTube Analytics API v2 (métricas) + YouTube Data API v3 (títulos, capas e duração).
 *
 * A API não expõe impressões nem CTR de miniatura, e receita exige o escopo
 * yt-analytics-monetary.readonly (não solicitado) — por isso nenhum dos dois aparece.
 */
class YouTubeAnalyticsProvider implements AnalyticsProviderInterface
{
    private const ANALYTICS_URL = 'https://youtubeanalytics.googleapis.com/v2/reports';

    private const DATA_API_URL = 'https://www.googleapis.com/youtube/v3';

    // Desde out/2024 Shorts podem ter até 3 minutos.
    private const SHORTS_MAX_SECONDS = 180;

    private const TOP_CONTENT_LIMIT = 25;

    private const BREAKDOWN_LIMIT = 6;

    private const OVERVIEW_METRICS = 'views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,subscribersGained,subscribersLost,likes,comments,shares';

    private const SERIES_METRICS = 'views,estimatedMinutesWatched,subscribersGained,subscribersLost,likes,comments,shares';

    private const TOP_CONTENT_METRICS = 'views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,likes,comments,shares,subscribersGained';

    private const TRAFFIC_SOURCES = [
        'YT_SEARCH' => 'Pesquisa do YouTube',
        'RELATED_VIDEO' => 'Vídeos sugeridos',
        'SUBSCRIBER' => 'Inscritos (navegação)',
        'BROWSE' => 'Página inicial e navegação',
        'SHORTS' => 'Feed de Shorts',
        'EXT_URL' => 'Sites externos',
        'PLAYLIST' => 'Playlists',
        'NOTIFICATION' => 'Notificações',
        'YT_CHANNEL' => 'Página do canal',
        'NO_LINK_OTHER' => 'Direto ou desconhecido',
        'YT_OTHER_PAGE' => 'Outras páginas do YouTube',
        'END_SCREEN' => 'Telas finais',
        'HASHTAGS' => 'Hashtags',
    ];

    private const CONTENT_TYPES = [
        'SHORTS' => 'Shorts',
        'VIDEO_ON_DEMAND' => 'Vídeos',
        'LIVE_STREAM' => 'Lives',
        'STORY' => 'Stories',
    ];

    public function capabilities(): array
    {
        return [
            'date_ranges' => ['last_7_days', 'last_28_days', 'last_90_days', 'this_month', 'last_month', 'last_365_days', 'lifetime', 'custom'],
            'max_range_days' => null,
            'has_daily_series' => true,
            'has_revenue' => false,
            'has_watch_time' => true,
            'engagement_benchmark' => 4.0,
            'best_times_metric' => 'views',
            'cache_minutes' => 360,
            'lifetime_start' => '2005-04-23',
        ];
    }

    public function dashboard(SocialAccount $account, string $token, AnalyticsPeriod $period): array
    {
        $client = $this->client($token);

        $current = $this->report($client, $period->start, $period->end, self::OVERVIEW_METRICS)[0] ?? [];
        $previous = $period->hasPrevious()
            ? ($this->report($client, $period->previousStart, $period->previousEnd, self::OVERVIEW_METRICS)[0] ?? [])
            : null;

        return [
            'overview' => $this->overview($current, $previous),
            'time_series' => $this->timeSeries($client, $period),
            'breakdowns' => array_values(array_filter([
                $this->breakdown($client, $period, 'insightTrafficSourceType', 'traffic_sources', 'Origem das visualizações', self::TRAFFIC_SOURCES),
                $this->breakdown($client, $period, 'creatorContentType', 'content_type', 'Visualizações por formato', self::CONTENT_TYPES),
            ])),
            'top_content' => $this->topContent($client, $period),
            'notes' => [
                'Os dados do YouTube Analytics podem ter atraso de até 2 dias.',
                'O ranking considera as visualizações no período, inclusive de vídeos publicados antes dele.',
            ],
        ];
    }

    public function recentContents(SocialAccount $account, string $token): array
    {
        $client = $this->client($token);

        $channel = $this->get($client, self::DATA_API_URL.'/channels', ['part' => 'contentDetails', 'mine' => 'true']);
        $uploadsPlaylist = $channel['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;

        if (! $uploadsPlaylist) {
            return [];
        }

        $playlist = $this->get($client, self::DATA_API_URL.'/playlistItems', [
            'part' => 'contentDetails',
            'playlistId' => $uploadsPlaylist,
            'maxResults' => 50,
        ]);

        $videoIds = collect($playlist['items'] ?? [])->pluck('contentDetails.videoId')->filter()->all();

        return collect($this->videos($client, $videoIds))
            ->map(fn (array $video) => [
                'published_at' => CarbonImmutable::parse($video['snippet']['publishedAt']),
                'value' => (int) ($video['statistics']['viewCount'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function snapshot(SocialAccount $account, string $token): ?array
    {
        // A API do YouTube guarda o histórico completo: não precisa de snapshot.
        return null;
    }

    private function overview(array $current, ?array $previous): array
    {
        $value = fn (?array $row, string $key) => $row === null ? null : (int) ($row[$key] ?? 0);
        $interactions = fn (?array $row) => $row === null ? null : $value($row, 'likes') + $value($row, 'comments') + $value($row, 'shares');
        $netFollowers = fn (?array $row) => $row === null ? null : $value($row, 'subscribersGained') - $value($row, 'subscribersLost');
        $engagement = fn (?array $row) => $row === null ? null : MetricsCalculator::engagementRate($interactions($row), $value($row, 'views'));
        $watchHours = fn (?array $row) => $row === null ? null : round($value($row, 'estimatedMinutesWatched') / 60, 1);
        $viewPercentage = fn (?array $row) => $row === null ? null : round((float) ($row['averageViewPercentage'] ?? 0), 1);

        return [
            MetricsCalculator::overviewItem('views', 'Visualizações', 'number', $value($current, 'views'), $value($previous, 'views')),
            MetricsCalculator::overviewItem('watch_time', 'Tempo de exibição', 'hours', $watchHours($current), $watchHours($previous)),
            MetricsCalculator::overviewItem('net_followers', 'Inscritos líquidos', 'number', $netFollowers($current), $netFollowers($previous), 'Inscritos ganhos menos perdidos no período.'),
            MetricsCalculator::overviewItem('engagement_rate', 'Engajamento', 'percent', $engagement($current), $engagement($previous), '(Curtidas + comentários + compartilhamentos) ÷ visualizações.'),
            MetricsCalculator::overviewItem('average_view_duration', 'Duração média assistida', 'duration', $value($current, 'averageViewDuration'), $value($previous, 'averageViewDuration')),
            MetricsCalculator::overviewItem('average_view_percentage', 'Retenção média', 'percent', $viewPercentage($current), $viewPercentage($previous), 'Percentual médio do vídeo assistido por visualização.'),
            MetricsCalculator::overviewItem('likes', 'Curtidas', 'number', $value($current, 'likes'), $value($previous, 'likes')),
            MetricsCalculator::overviewItem('comments', 'Comentários', 'number', $value($current, 'comments'), $value($previous, 'comments')),
            MetricsCalculator::overviewItem('shares', 'Compartilhamentos', 'number', $value($current, 'shares'), $value($previous, 'shares')),
        ];
    }

    private function timeSeries(PendingRequest $client, AnalyticsPeriod $period): array
    {
        $dimension = $period->granularity();

        $current = $this->seriesRows($client, $period->start, $period->end, $dimension);
        $previous = $period->hasPrevious() ? $this->seriesRows($client, $period->previousStart, $period->previousEnd, $dimension) : null;

        $buckets = $period->buckets();
        $previousBuckets = $period->previousBuckets();

        // Em "todo o período" ignoramos os meses antes do primeiro dado do canal.
        if (! $period->hasPrevious()) {
            $firstWithData = collect($buckets)->search(fn (string $bucket) => isset($current[$bucket]));
            $buckets = $firstWithData === false ? $buckets : array_slice($buckets, $firstWithData);
        }

        $series = [
            ['key' => 'views', 'label' => 'Visualizações', 'format' => 'number', 'type' => 'area', 'pick' => fn (array $row) => (int) $row['views']],
            ['key' => 'watch_time', 'label' => 'Tempo de exibição (h)', 'format' => 'hours', 'type' => 'area', 'pick' => fn (array $row) => round($row['estimatedMinutesWatched'] / 60, 1)],
            ['key' => 'net_followers', 'label' => 'Inscritos líquidos', 'format' => 'number', 'type' => 'bar', 'pick' => fn (array $row) => (int) $row['subscribersGained'] - (int) $row['subscribersLost']],
            ['key' => 'interactions', 'label' => 'Interações', 'format' => 'number', 'type' => 'bar', 'pick' => fn (array $row) => (int) $row['likes'] + (int) $row['comments'] + (int) $row['shares']],
        ];

        return [
            'dates' => $buckets,
            'series' => collect($series)->map(fn (array $definition) => [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'format' => $definition['format'],
                'type' => $definition['type'],
                'current' => array_map(fn (string $bucket) => isset($current[$bucket]) ? $definition['pick']($current[$bucket]) : 0, $buckets),
                'previous' => $previous === null
                    ? null
                    : array_map(fn (string $bucket) => isset($previous[$bucket]) ? $definition['pick']($previous[$bucket]) : 0, $previousBuckets),
            ])->all(),
        ];
    }

    /**
     * Linhas da série indexadas pelo bucket ('Y-m-d' ou 'Y-m').
     */
    private function seriesRows(PendingRequest $client, CarbonImmutable $start, CarbonImmutable $end, string $dimension): array
    {
        // Com dimensão mensal a API exige o primeiro e o último dia do mês.
        if ($dimension === 'month') {
            $start = $start->startOfMonth();
            $end = $end->endOfMonth();
        }

        return collect($this->report($client, $start, $end, self::SERIES_METRICS, ['dimensions' => $dimension, 'sort' => $dimension]))
            ->keyBy($dimension)
            ->all();
    }

    /**
     * Quebra de visualizações por uma dimensão. Se a API não aceitar a dimensão
     * para o canal, a seção é omitida (permissão continua gerando reauth).
     */
    private function breakdown(PendingRequest $client, AnalyticsPeriod $period, string $dimension, string $key, string $label, array $labels): ?array
    {
        try {
            $rows = $this->report($client, $period->start, $period->end, 'views', ['dimensions' => $dimension, 'sort' => '-views']);
        } catch (AnalyticsException $e) {
            throw $e;
        } catch (Throwable) {
            return null;
        }

        $items = collect($rows)
            ->filter(fn (array $row) => (int) $row['views'] > 0)
            ->take(self::BREAKDOWN_LIMIT)
            ->map(fn (array $row) => [
                'key' => $row[$dimension],
                'label' => $labels[$row[$dimension]] ?? ucfirst(strtolower(str_replace('_', ' ', $row[$dimension]))),
                'value' => (int) $row['views'],
            ])
            ->values()
            ->all();

        return empty($items) ? null : ['key' => $key, 'label' => $label, 'items' => $items];
    }

    private function topContent(PendingRequest $client, AnalyticsPeriod $period): array
    {
        $rows = $this->report($client, $period->start, $period->end, self::TOP_CONTENT_METRICS, [
            'dimensions' => 'video',
            'sort' => '-views',
            'maxResults' => self::TOP_CONTENT_LIMIT,
        ]);

        $videos = collect($this->videos($client, array_column($rows, 'video')))->keyBy('id');

        $items = collect($rows)->map(function (array $row) use ($videos) {
            $video = $videos->get($row['video'], []);
            $isShort = $this->durationInSeconds($video['contentDetails']['duration'] ?? null) <= self::SHORTS_MAX_SECONDS;
            $views = (int) $row['views'];
            $interactions = (int) $row['likes'] + (int) $row['comments'] + (int) $row['shares'];

            return [
                'id' => $row['video'],
                'title' => $video['snippet']['title'] ?? 'Vídeo indisponível',
                'thumbnail_url' => $video['snippet']['thumbnails']['high']['url'] ?? $video['snippet']['thumbnails']['default']['url'] ?? null,
                'permalink' => $isShort ? "https://www.youtube.com/shorts/{$row['video']}" : "https://www.youtube.com/watch?v={$row['video']}",
                'published_at' => $video['snippet']['publishedAt'] ?? null,
                'format' => $isShort ? 'short' : 'video',
                'metrics' => [
                    'views' => $views,
                    'watch_time' => round($row['estimatedMinutesWatched'] / 60, 1),
                    'average_view_percentage' => round((float) $row['averageViewPercentage'], 1),
                    'likes' => (int) $row['likes'],
                    'comments' => (int) $row['comments'],
                    'shares' => (int) $row['shares'],
                    'subscribers_gained' => (int) $row['subscribersGained'],
                    'engagement_rate' => MetricsCalculator::engagementRate($interactions, $views),
                ],
            ];
        })->values()->all();

        return [
            'columns' => [
                ['key' => 'views', 'label' => 'Visualizações', 'format' => 'number'],
                ['key' => 'watch_time', 'label' => 'Tempo (h)', 'format' => 'hours'],
                ['key' => 'average_view_percentage', 'label' => 'Retenção', 'format' => 'percent'],
                ['key' => 'likes', 'label' => 'Curtidas', 'format' => 'number'],
                ['key' => 'comments', 'label' => 'Comentários', 'format' => 'number'],
                ['key' => 'engagement_rate', 'label' => 'Engajamento', 'format' => 'percent'],
            ],
            'items' => $items,
        ];
    }

    /**
     * Detalhes dos vídeos (Data API aceita até 50 ids por chamada).
     */
    private function videos(PendingRequest $client, array $videoIds): array
    {
        return collect($videoIds)
            ->chunk(50)
            ->flatMap(fn ($chunk) => $this->get($client, self::DATA_API_URL.'/videos', [
                'part' => 'snippet,contentDetails,statistics',
                'id' => $chunk->implode(','),
            ])['items'] ?? [])
            ->all();
    }

    /**
     * Consulta o relatório e devolve as linhas como arrays associativos (pelo nome da coluna).
     */
    private function report(PendingRequest $client, CarbonImmutable $start, CarbonImmutable $end, string $metrics, array $params = []): array
    {
        $response = $this->get($client, self::ANALYTICS_URL, array_merge([
            'ids' => 'channel==MINE',
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'metrics' => $metrics,
        ], $params));

        $columns = array_column($response['columnHeaders'] ?? [], 'name');

        return array_map(fn (array $row) => array_combine($columns, $row), $response['rows'] ?? []);
    }

    private function get(PendingRequest $client, string $url, array $query): array
    {
        $response = $client->get($url, $query);
        $this->ensureSuccessful($response);

        return $response->json() ?? [];
    }

    /**
     * 401 e 403 de permissão = token sem acesso ao Analytics (reconectar).
     * Cota estourada e demais falhas = indisponibilidade temporária.
     */
    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = $response->json('error.errors.0.reason');

        if ($response->status() === 401 || ($response->status() === 403 && ! in_array($reason, ['quotaExceeded', 'rateLimitExceeded', 'dailyLimitExceeded'], true))) {
            throw AnalyticsException::reauthRequired(Platform::YOUTUBE);
        }

        throw new RuntimeException("YouTube API respondeu {$response->status()}: ".$response->body());
    }

    private function durationInSeconds(?string $isoDuration): int
    {
        if (! $isoDuration) {
            return PHP_INT_MAX;
        }

        try {
            $interval = new DateInterval($isoDuration);
        } catch (Throwable) {
            return PHP_INT_MAX;
        }

        return ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(20);
    }
}
