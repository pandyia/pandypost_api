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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TikTok Display API v2 (escopos user.info.stats e video.list).
 *
 * A API só informa totais acumulados: por isso as métricas de conteúdo
 * consideram os vídeos PUBLICADOS no período (acumulado desde a publicação),
 * e o crescimento de seguidores/curtidas vem dos snapshots diários.
 */
class TikTokAnalyticsProvider implements AnalyticsProviderInterface
{
    private const API_URL = 'https://open.tiktokapis.com/v2';

    private const USER_FIELDS = 'open_id,display_name,avatar_url,follower_count,following_count,likes_count,video_count';

    private const VIDEO_FIELDS = 'id,title,video_description,create_time,cover_image_url,share_url,duration,view_count,like_count,comment_count,share_count';

    // A API devolve no máximo 20 vídeos por página (mais recentes primeiro).
    private const VIDEOS_PER_PAGE = 20;

    private const MAX_PAGES = 10;

    private const TOP_CONTENT_LIMIT = 25;

    private const REAUTH_ERROR_CODES = ['access_token_invalid', 'scope_not_authorized', 'scope_permission_missed', 'invalid_token', 'token_expired'];

    private const DURATION_BUCKETS = [
        ['key' => 'up_to_15s', 'label' => 'Até 15s', 'max' => 15],
        ['key' => 'up_to_60s', 'label' => '16s a 1min', 'max' => 60],
        ['key' => 'up_to_3min', 'label' => '1 a 3min', 'max' => 180],
        ['key' => 'over_3min', 'label' => 'Mais de 3min', 'max' => PHP_INT_MAX],
    ];

    public function __construct(
        private readonly SnapshotHistory $snapshots,
    ) {}

    public function capabilities(): array
    {
        return [
            'date_ranges' => ['last_7_days', 'last_28_days', 'last_90_days', 'custom'],
            'max_range_days' => 365,
            'has_daily_series' => false,
            'has_revenue' => false,
            'has_watch_time' => false,
            'engagement_benchmark' => 6.0,
            'best_times_metric' => 'views',
            'cache_minutes' => 60,
            'lifetime_start' => null,
        ];
    }

    public function dashboard(SocialAccount $account, string $token, AnalyticsPeriod $period): array
    {
        $client = $this->client($token);
        $user = $this->user($client);
        $videos = $this->videosSince($client, $period->previousStart);

        $current = $videos->filter(fn (array $video) => $period->contains($this->publishedAt($video)));
        $previous = $videos->filter(fn (array $video) => $period->containsPrevious($this->publishedAt($video)));

        return [
            'overview' => $this->overview($account, $period, $user, $current, $previous),
            'time_series' => $this->timeSeries($account, $period, $current, $previous),
            'breakdowns' => array_values(array_filter([$this->durationBreakdown($current)])),
            'top_content' => $this->topContent($current),
            'notes' => [
                'O TikTok não informa visualizações por dia: os números dos vídeos são acumulados desde a publicação e consideram os vídeos publicados no período.',
                'Seguidores e curtidas ganhos vêm do histórico diário que o Pandypost guarda desde a conexão da conta.',
            ],
        ];
    }

    public function recentContents(SocialAccount $account, string $token): array
    {
        return $this->videosSince($this->client($token), CarbonImmutable::now()->subYear(), maxPages: 3)
            ->map(fn (array $video) => [
                'published_at' => $this->publishedAt($video),
                'value' => (int) ($video['view_count'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function snapshot(SocialAccount $account, string $token): ?array
    {
        $user = $this->user($this->client($token));

        return [
            'followers' => $user['follower_count'] ?? null,
            'following' => $user['following_count'] ?? null,
            'media_count' => $user['video_count'] ?? null,
            'total_likes' => $user['likes_count'] ?? null,
        ];
    }

    private function overview(SocialAccount $account, AnalyticsPeriod $period, array $user, Collection $current, Collection $previous): array
    {
        $sum = fn (Collection $videos, string $field) => (int) $videos->sum($field);
        $interactions = fn (Collection $videos) => $sum($videos, 'like_count') + $sum($videos, 'comment_count') + $sum($videos, 'share_count');

        $items = [
            MetricsCalculator::overviewItem('views', 'Visualizações', 'number', $sum($current, 'view_count'), $sum($previous, 'view_count'), 'Dos vídeos publicados no período (acumulado desde a publicação).'),
            MetricsCalculator::overviewItem('posts', 'Vídeos publicados', 'number', $current->count(), $previous->count()),
            MetricsCalculator::overviewItem('engagement_rate', 'Engajamento', 'percent', MetricsCalculator::engagementRate($interactions($current), $sum($current, 'view_count')), MetricsCalculator::engagementRate($interactions($previous), $sum($previous, 'view_count')), '(Curtidas + comentários + compartilhamentos) ÷ visualizações.'),
            MetricsCalculator::overviewItem('followers', 'Seguidores', 'number', $user['follower_count'] ?? null, $this->snapshots->valueBefore($account, 'followers', $period->start), 'Total atual, comparado ao início do período.'),
        ];

        $netFollowers = $this->snapshots->delta($account, 'followers', $period->start, $period->end, $user['follower_count'] ?? null);

        if ($netFollowers !== null) {
            $items[] = MetricsCalculator::overviewItem('net_followers', 'Seguidores líquidos', 'number', $netFollowers, $this->snapshots->delta($account, 'followers', $period->previousStart, $period->previousEnd));
        }

        $likesGained = $this->snapshots->delta($account, 'total_likes', $period->start, $period->end, $user['likes_count'] ?? null);

        if ($likesGained !== null) {
            $items[] = MetricsCalculator::overviewItem('likes_gained', 'Curtidas ganhas', 'number', $likesGained, $this->snapshots->delta($account, 'total_likes', $period->previousStart, $period->previousEnd), 'Em todos os vídeos da conta, no período.');
        }

        return array_merge($items, [
            MetricsCalculator::overviewItem('likes', 'Curtidas', 'number', $sum($current, 'like_count'), $sum($previous, 'like_count'), 'Dos vídeos publicados no período.'),
            MetricsCalculator::overviewItem('comments', 'Comentários', 'number', $sum($current, 'comment_count'), $sum($previous, 'comment_count')),
            MetricsCalculator::overviewItem('shares', 'Compartilhamentos', 'number', $sum($current, 'share_count'), $sum($previous, 'share_count')),
            MetricsCalculator::overviewItem('total_likes', 'Curtidas na conta', 'number', $user['likes_count'] ?? null, null, 'Total acumulado em todos os vídeos.'),
        ]);
    }

    private function timeSeries(SocialAccount $account, AnalyticsPeriod $period, Collection $current, Collection $previous): array
    {
        $byDay = fn (Collection $videos, array $buckets, string $field) => array_map(
            fn (string $date) => (int) $videos
                ->filter(fn (array $video) => $this->publishedAt($video)->setTimezone(MetricsCalculator::TIMEZONE)->toDateString() === $date)
                ->sum($field),
            $buckets,
        );
        $postsByDay = fn (Collection $videos, array $buckets) => array_map(
            fn (string $date) => $videos
                ->filter(fn (array $video) => $this->publishedAt($video)->setTimezone(MetricsCalculator::TIMEZONE)->toDateString() === $date)
                ->count(),
            $buckets,
        );

        $buckets = $period->buckets();
        $previousBuckets = $period->previousBuckets();

        $series = [
            ['key' => 'views', 'label' => 'Visualizações (por dia de publicação)', 'format' => 'number', 'type' => 'bar', 'current' => $byDay($current, $buckets, 'view_count'), 'previous' => $byDay($previous, $previousBuckets, 'view_count')],
            ['key' => 'posts', 'label' => 'Vídeos publicados', 'format' => 'number', 'type' => 'bar', 'current' => $postsByDay($current, $buckets), 'previous' => $postsByDay($previous, $previousBuckets)],
        ];

        $followers = $this->snapshots->dailySeries($account, 'followers', $period);

        if ($followers !== null) {
            $series[] = ['key' => 'followers', 'label' => 'Seguidores', 'format' => 'number', 'type' => 'line', 'current' => $followers, 'previous' => null];
        }

        return ['dates' => $buckets, 'series' => $series];
    }

    private function durationBreakdown(Collection $videos): ?array
    {
        $items = collect(self::DURATION_BUCKETS)
            ->map(function (array $bucket, int $index) use ($videos) {
                $min = $index === 0 ? 0 : self::DURATION_BUCKETS[$index - 1]['max'] + 1;

                return [
                    'key' => $bucket['key'],
                    'label' => $bucket['label'],
                    'value' => (int) $videos
                        ->filter(fn (array $video) => ($video['duration'] ?? 0) >= $min && ($video['duration'] ?? 0) <= $bucket['max'])
                        ->sum('view_count'),
                ];
            })
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values()
            ->all();

        return empty($items) ? null : ['key' => 'duration', 'label' => 'Visualizações por duração do vídeo', 'items' => $items];
    }

    private function topContent(Collection $videos): array
    {
        $items = $videos
            ->sortByDesc('view_count')
            ->take(self::TOP_CONTENT_LIMIT)
            ->map(function (array $video) {
                $views = (int) ($video['view_count'] ?? 0);
                $likes = (int) ($video['like_count'] ?? 0);
                $comments = (int) ($video['comment_count'] ?? 0);
                $shares = (int) ($video['share_count'] ?? 0);

                return [
                    'id' => $video['id'],
                    'title' => $this->title($video),
                    'thumbnail_url' => $video['cover_image_url'] ?? null,
                    'permalink' => $video['share_url'] ?? null,
                    'published_at' => $this->publishedAt($video)->toIso8601String(),
                    'format' => 'video',
                    'metrics' => [
                        'views' => $views,
                        'likes' => $likes,
                        'comments' => $comments,
                        'shares' => $shares,
                        'duration' => (int) ($video['duration'] ?? 0),
                        'engagement_rate' => MetricsCalculator::engagementRate($likes + $comments + $shares, $views),
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'columns' => [
                ['key' => 'views', 'label' => 'Visualizações', 'format' => 'number'],
                ['key' => 'likes', 'label' => 'Curtidas', 'format' => 'number'],
                ['key' => 'comments', 'label' => 'Comentários', 'format' => 'number'],
                ['key' => 'shares', 'label' => 'Compartilhamentos', 'format' => 'number'],
                ['key' => 'engagement_rate', 'label' => 'Engajamento', 'format' => 'percent'],
            ],
            'items' => $items,
        ];
    }

    private function user(PendingRequest $client): array
    {
        $response = $client->get(self::API_URL.'/user/info/', ['fields' => self::USER_FIELDS]);
        $this->ensureSuccessful($response);

        return $response->json('data.user', []);
    }

    /**
     * Vídeos publicados desde $since (a lista vem do mais novo para o mais antigo).
     */
    private function videosSince(PendingRequest $client, CarbonImmutable $since, int $maxPages = self::MAX_PAGES): Collection
    {
        $videos = collect();
        $cursor = null;

        for ($page = 0; $page < $maxPages; $page++) {
            $response = $client->post(
                self::API_URL.'/video/list/?fields='.self::VIDEO_FIELDS,
                array_filter(['max_count' => self::VIDEOS_PER_PAGE, 'cursor' => $cursor]),
            );
            $this->ensureSuccessful($response);

            $batch = collect($response->json('data.videos', []));
            $videos = $videos->merge($batch);
            $cursor = $response->json('data.cursor');

            $reachedOlderVideos = $batch->isNotEmpty() && $this->publishedAt($batch->last())->lt($since->startOfDay());

            if (! $response->json('data.has_more') || $reachedOlderVideos) {
                break;
            }
        }

        return $videos->filter(fn (array $video) => $this->publishedAt($video)->gte($since->startOfDay()))->values();
    }

    private function publishedAt(array $video): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp((int) ($video['create_time'] ?? 0));
    }

    private function title(array $video): string
    {
        $text = trim($video['title'] ?? '') ?: trim(Str::before($video['video_description'] ?? '', "\n"));

        return $text === '' ? 'Vídeo sem descrição' : Str::limit($text, 80);
    }

    /**
     * O TikTok responde {error: {code: "ok"}} no sucesso; qualquer outro código é falha.
     */
    private function ensureSuccessful(Response $response): void
    {
        $code = $response->json('error.code');

        if ($response->successful() && ($code === null || $code === 'ok')) {
            return;
        }

        if ($response->status() === 401 || in_array($code, self::REAUTH_ERROR_CODES, true)) {
            throw AnalyticsException::reauthRequired(Platform::TIKTOK);
        }

        throw new RuntimeException("TikTok API respondeu {$response->status()}: ".$response->body());
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(20);
    }
}
