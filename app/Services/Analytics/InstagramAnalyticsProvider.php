<?php

namespace App\Services\Analytics;

use App\Contracts\AnalyticsProviderInterface;
use App\Enums\Platform;
use App\Exceptions\AnalyticsException;
use App\Models\SocialAccount;
use App\Support\Analytics\AnalyticsPeriod;
use App\Support\Analytics\MetricsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Instagram API com Instagram Login (graph.instagram.com).
 * Exige o escopo instagram_business_manage_insights.
 *
 * Limites da API: consultas de insights com no máximo 30 dias; série diária só
 * para alcance; dados com atraso de até 48h; "impressions" foi descontinuado
 * (substituído por "views").
 */
class InstagramAnalyticsProvider implements AnalyticsProviderInterface
{
    private const GRAPH_URL = 'https://graph.instagram.com/v25.0';

    private const ACCOUNT_METRICS = 'views,reach,accounts_engaged,total_interactions,likes,comments,shares,saves,profile_links_taps';

    private const MEDIA_FIELDS = 'id,caption,media_type,media_product_type,permalink,thumbnail_url,media_url,timestamp,like_count,comments_count';

    // Insights por mídia custam 1 chamada cada: só para os melhores do período.
    private const TOP_CONTENT_LIMIT = 12;

    private const MEDIA_PAGE_LIMIT = 50;

    // Códigos de erro da Meta que significam token inválido ou permissão faltando.
    private const REAUTH_ERROR_CODES = [10, 102, 190, 200];

    private const CONTENT_TYPES = [
        'REELS' => 'Reels',
        'FEED' => 'Feed',
        'STORY' => 'Stories',
        'AD' => 'Anúncios',
    ];

    public function __construct(
        private readonly SnapshotHistory $snapshots,
    ) {}

    public function capabilities(): array
    {
        return [
            'date_ranges' => ['last_7_days', 'last_28_days', 'custom'],
            'max_range_days' => 30,
            'has_daily_series' => true,
            'has_revenue' => false,
            'has_watch_time' => false,
            'engagement_benchmark' => 3.0,
            'best_times_metric' => 'interactions',
            'cache_minutes' => 180,
            'lifetime_start' => null,
        ];
    }

    public function dashboard(SocialAccount $account, string $token, AnalyticsPeriod $period): array
    {
        $client = $this->client($token);
        $userId = $account->platform_id;

        $profile = $this->get($client, "/{$userId}", ['fields' => 'followers_count,follows_count,media_count']);
        $current = $this->accountTotals($client, $userId, $period->start, $period->end);
        $previous = $this->accountTotals($client, $userId, $period->previousStart, $period->previousEnd);

        $netFollowers = $this->netFollowers($client, $account, $period->start, $period->end, $profile['followers_count'] ?? null);
        $previousNetFollowers = $this->netFollowers($client, $account, $period->previousStart, $period->previousEnd);

        $notes = ['Os dados do Instagram podem ter atraso de até 48 horas.'];

        if ($netFollowers === null) {
            $notes[] = 'Seguidores ganhos aparecem para contas com 100+ seguidores ou a partir do histórico diário que o Pandypost passa a guardar.';
        }

        return [
            'overview' => $this->overview($current, $previous, $profile, $account, $period, $netFollowers, $previousNetFollowers),
            'time_series' => $this->timeSeries($client, $account, $userId, $period),
            'breakdowns' => array_values(array_filter([$this->contentTypeBreakdown($client, $userId, $period)])),
            'top_content' => $this->topContent($client, $userId, $period),
            'notes' => $notes,
        ];
    }

    public function recentContents(SocialAccount $account, string $token): array
    {
        // Views por post exigiriam 1 chamada por mídia: usamos as interações públicas.
        return collect($this->media($this->client($token), $account->platform_id))
            ->map(fn (array $media) => [
                'published_at' => CarbonImmutable::parse($media['timestamp']),
                'value' => (int) ($media['like_count'] ?? 0) + (int) ($media['comments_count'] ?? 0),
            ])
            ->all();
    }

    public function snapshot(SocialAccount $account, string $token): ?array
    {
        $profile = $this->get($this->client($token), "/{$account->platform_id}", ['fields' => 'followers_count,follows_count,media_count']);

        return [
            'followers' => $profile['followers_count'] ?? null,
            'following' => $profile['follows_count'] ?? null,
            'media_count' => $profile['media_count'] ?? null,
            'total_likes' => null,
        ];
    }

    private function overview(array $current, array $previous, array $profile, SocialAccount $account, AnalyticsPeriod $period, ?int $netFollowers, ?int $previousNetFollowers): array
    {
        $engagement = fn (array $totals) => MetricsCalculator::engagementRate($totals['total_interactions'] ?? 0, $totals['views'] ?? 0);
        $followersBefore = $this->snapshots->valueBefore($account, 'followers', $period->start);

        $items = [
            MetricsCalculator::overviewItem('views', 'Visualizações', 'number', $current['views'] ?? 0, $previous['views'] ?? 0, 'Vezes que seus conteúdos foram exibidos (substitui as impressões).'),
            MetricsCalculator::overviewItem('reach', 'Alcance', 'number', $current['reach'] ?? 0, $previous['reach'] ?? 0, 'Contas únicas que viram seus conteúdos.'),
            MetricsCalculator::overviewItem('accounts_engaged', 'Contas engajadas', 'number', $current['accounts_engaged'] ?? 0, $previous['accounts_engaged'] ?? 0, 'Contas únicas que interagiram com seus conteúdos.'),
            MetricsCalculator::overviewItem('engagement_rate', 'Engajamento', 'percent', $engagement($current), $engagement($previous), 'Interações ÷ visualizações.'),
            MetricsCalculator::overviewItem('followers', 'Seguidores', 'number', $profile['followers_count'] ?? null, $followersBefore, 'Total atual, comparado ao início do período.'),
        ];

        if ($netFollowers !== null) {
            $items[] = MetricsCalculator::overviewItem('net_followers', 'Seguidores líquidos', 'number', $netFollowers, $previousNetFollowers, 'Novos seguidores menos deixaram de seguir.');
        }

        return array_merge($items, [
            MetricsCalculator::overviewItem('total_interactions', 'Interações', 'number', $current['total_interactions'] ?? 0, $previous['total_interactions'] ?? 0),
            MetricsCalculator::overviewItem('likes', 'Curtidas', 'number', $current['likes'] ?? 0, $previous['likes'] ?? 0),
            MetricsCalculator::overviewItem('comments', 'Comentários', 'number', $current['comments'] ?? 0, $previous['comments'] ?? 0),
            MetricsCalculator::overviewItem('shares', 'Compartilhamentos', 'number', $current['shares'] ?? 0, $previous['shares'] ?? 0),
            MetricsCalculator::overviewItem('saves', 'Salvamentos', 'number', $current['saves'] ?? 0, $previous['saves'] ?? 0),
            MetricsCalculator::overviewItem('profile_links_taps', 'Toques no perfil', 'number', $current['profile_links_taps'] ?? 0, $previous['profile_links_taps'] ?? 0, 'Toques nos botões e links do perfil.'),
        ]);
    }

    /**
     * Totais da conta no intervalo: ['views' => 10, 'reach' => 5, ...].
     */
    private function accountTotals(PendingRequest $client, string $userId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $response = $this->get($client, "/{$userId}/insights", array_merge([
            'metric' => self::ACCOUNT_METRICS,
            'period' => 'day',
            'metric_type' => 'total_value',
        ], $this->range($start, $end)));

        return collect($response['data'] ?? [])
            ->mapWithKeys(fn (array $metric) => [$metric['name'] => (int) ($metric['total_value']['value'] ?? 0)])
            ->all();
    }

    /**
     * Seguidores ganhos − perdidos. A API só informa para contas com 100+ seguidores;
     * sem isso, usamos a diferença entre snapshots.
     */
    private function netFollowers(PendingRequest $client, SocialAccount $account, CarbonImmutable $start, CarbonImmutable $end, ?int $currentFollowers = null): ?int
    {
        $response = $this->get($client, "/{$account->platform_id}/insights", array_merge([
            'metric' => 'follows_and_unfollows',
            'period' => 'day',
            'metric_type' => 'total_value',
            'breakdown' => 'follow_type',
        ], $this->range($start, $end)));

        $results = collect($response['data'][0]['total_value']['breakdowns'][0]['results'] ?? []);

        if ($results->isNotEmpty()) {
            $byType = $results->mapWithKeys(fn (array $result) => [$result['dimension_values'][0] => (int) $result['value']]);

            return ($byType['FOLLOWER'] ?? 0) - ($byType['NON_FOLLOWER'] ?? 0);
        }

        return $this->snapshots->delta($account, 'followers', $start, $end, $currentFollowers);
    }

    private function timeSeries(PendingRequest $client, SocialAccount $account, string $userId, AnalyticsPeriod $period): array
    {
        $buckets = $period->buckets();

        $series = [[
            'key' => 'reach',
            'label' => 'Alcance',
            'format' => 'number',
            'type' => 'area',
            'current' => $this->dailyReach($client, $userId, $period->start, $period->end, $buckets),
            'previous' => $this->dailyReach($client, $userId, $period->previousStart, $period->previousEnd, $period->previousBuckets()),
        ]];

        $followers = $this->snapshots->dailySeries($account, 'followers', $period);

        if ($followers !== null) {
            $series[] = ['key' => 'followers', 'label' => 'Seguidores', 'format' => 'number', 'type' => 'line', 'current' => $followers, 'previous' => null];
        }

        return ['dates' => $buckets, 'series' => $series];
    }

    private function dailyReach(PendingRequest $client, string $userId, CarbonImmutable $start, CarbonImmutable $end, array $buckets): array
    {
        $response = $this->get($client, "/{$userId}/insights", array_merge([
            'metric' => 'reach',
            'period' => 'day',
            'metric_type' => 'time_series',
        ], $this->range($start, $end)));

        // end_time marca o fim do dia medido: o valor pertence ao dia anterior.
        $byDate = collect($response['data'][0]['values'] ?? [])
            ->mapWithKeys(fn (array $point) => [
                CarbonImmutable::parse($point['end_time'])->subDay()->toDateString() => (int) $point['value'],
            ]);

        return array_map(fn (string $date) => $byDate[$date] ?? 0, $buckets);
    }

    private function contentTypeBreakdown(PendingRequest $client, string $userId, AnalyticsPeriod $period): ?array
    {
        $response = $this->get($client, "/{$userId}/insights", array_merge([
            'metric' => 'views',
            'period' => 'day',
            'metric_type' => 'total_value',
            'breakdown' => 'media_product_type',
        ], $this->range($period->start, $period->end)));

        $items = collect($response['data'][0]['total_value']['breakdowns'][0]['results'] ?? [])
            ->map(fn (array $result) => [
                'key' => $result['dimension_values'][0],
                'label' => self::CONTENT_TYPES[$result['dimension_values'][0]] ?? Str::title($result['dimension_values'][0]),
                'value' => (int) $result['value'],
            ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();

        return empty($items) ? null : ['key' => 'content_type', 'label' => 'Visualizações por formato', 'items' => $items];
    }

    private function topContent(PendingRequest $client, string $userId, AnalyticsPeriod $period): array
    {
        $items = collect($this->media($client, $userId))
            ->filter(fn (array $media) => $period->contains(CarbonImmutable::parse($media['timestamp'])))
            ->sortByDesc(fn (array $media) => (int) ($media['like_count'] ?? 0) + (int) ($media['comments_count'] ?? 0))
            ->take(self::TOP_CONTENT_LIMIT)
            ->map(fn (array $media) => $this->contentItem($client, $media))
            ->sortByDesc('metrics.views')
            ->values()
            ->all();

        return [
            'columns' => [
                ['key' => 'views', 'label' => 'Visualizações', 'format' => 'number'],
                ['key' => 'reach', 'label' => 'Alcance', 'format' => 'number'],
                ['key' => 'likes', 'label' => 'Curtidas', 'format' => 'number'],
                ['key' => 'comments', 'label' => 'Comentários', 'format' => 'number'],
                ['key' => 'saved', 'label' => 'Salvos', 'format' => 'number'],
                ['key' => 'engagement_rate', 'label' => 'Engajamento', 'format' => 'percent'],
            ],
            'items' => $items,
        ];
    }

    private function contentItem(PendingRequest $client, array $media): array
    {
        $isReel = ($media['media_product_type'] ?? null) === 'REELS';
        $insights = $this->mediaInsights($client, $media['id'], $isReel);

        $likes = $insights['likes'] ?? (int) ($media['like_count'] ?? 0);
        $comments = $insights['comments'] ?? (int) ($media['comments_count'] ?? 0);
        $shares = $insights['shares'] ?? 0;
        $saved = $insights['saved'] ?? 0;
        $views = $insights['views'] ?? 0;

        $metrics = [
            'views' => $views,
            'reach' => $insights['reach'] ?? 0,
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $shares,
            'saved' => $saved,
            'engagement_rate' => MetricsCalculator::engagementRate($likes + $comments + $shares + $saved, $views),
        ];

        if ($isReel && isset($insights['ig_reels_avg_watch_time'])) {
            // A API devolve milissegundos.
            $metrics['average_watch_time'] = round($insights['ig_reels_avg_watch_time'] / 1000, 1);
        }

        return [
            'id' => $media['id'],
            'title' => $this->title($media['caption'] ?? null),
            'thumbnail_url' => $media['thumbnail_url'] ?? $media['media_url'] ?? null,
            'permalink' => $media['permalink'] ?? null,
            'published_at' => CarbonImmutable::parse($media['timestamp'])->toIso8601String(),
            'format' => match (true) {
                $isReel => 'reel',
                ($media['media_type'] ?? null) === 'CAROUSEL_ALBUM' => 'carousel',
                default => 'post',
            },
            'metrics' => $metrics,
        ];
    }

    /**
     * Insights de uma mídia. Mídia antiga ou sem suporte a alguma métrica não
     * derruba o dashboard: o post aparece com os números públicos.
     */
    private function mediaInsights(PendingRequest $client, string $mediaId, bool $isReel): array
    {
        $metrics = 'views,reach,likes,comments,shares,saved,total_interactions'.($isReel ? ',ig_reels_avg_watch_time' : '');

        try {
            $response = $this->get($client, "/{$mediaId}/insights", ['metric' => $metrics]);
        } catch (AnalyticsException $e) {
            throw $e;
        } catch (Throwable) {
            return [];
        }

        return collect($response['data'] ?? [])
            ->mapWithKeys(fn (array $metric) => [
                $metric['name'] => (int) ($metric['values'][0]['value'] ?? $metric['total_value']['value'] ?? 0),
            ])
            ->all();
    }

    private function media(PendingRequest $client, string $userId): array
    {
        return $this->get($client, "/{$userId}/media", [
            'fields' => self::MEDIA_FIELDS,
            'limit' => self::MEDIA_PAGE_LIMIT,
        ])['data'] ?? [];
    }

    private function title(?string $caption): string
    {
        $firstLine = trim(Str::before((string) $caption, "\n"));

        return $firstLine === '' ? 'Post sem legenda' : Str::limit($firstLine, 80);
    }

    /**
     * since/until em timestamp Unix (o intervalo é inclusivo).
     */
    private function range(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'since' => $start->startOfDay()->getTimestamp(),
            'until' => $end->endOfDay()->getTimestamp(),
        ];
    }

    private function get(PendingRequest $client, string $path, array $query): array
    {
        $response = $client->get(self::GRAPH_URL.$path, $query);
        $this->ensureSuccessful($response);

        return $response->json() ?? [];
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $code = (int) $response->json('error.code');

        if ($response->status() === 401 || in_array($code, self::REAUTH_ERROR_CODES, true) || ($code >= 200 && $code <= 299)) {
            throw AnalyticsException::reauthRequired(Platform::INSTAGRAM);
        }

        throw new RuntimeException("Instagram API respondeu {$response->status()}: ".$response->body());
    }

    private function client(string $token): PendingRequest
    {
        // Mesmo padrão do InstagramService: token como parâmetro access_token.
        return Http::withQueryParameters(['access_token' => $token])->acceptJson()->timeout(20);
    }
}
