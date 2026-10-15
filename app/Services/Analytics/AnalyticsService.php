<?php

namespace App\Services\Analytics;

use App\Contracts\AnalyticsProviderInterface;
use App\Enums\Platform;
use App\Exceptions\AnalyticsException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Factories\AnalyticsProviderFactory;
use App\Support\Analytics\AnalyticsPeriod;
use App\Support\Analytics\MetricsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AnalyticsService
{
    // Atualização manual: 1 vez a cada 10 minutos por conta e usuário.
    private const REFRESH_COOLDOWN_SECONDS = 600;

    private const BEST_TIMES_CACHE_DAYS = 7;

    // Variação de visualizações (em %) que gera alerta.
    private const VIEWS_ALERT_THRESHOLD = 20;

    private const STALE_ALERT_DAYS = 7;

    public function __construct(
        private readonly AnalyticsProviderFactory $factory,
    ) {}

    public function capabilities(SocialAccount $account): array
    {
        return $this->provider($account)->capabilities();
    }

    public function resolvePeriod(SocialAccount $account, array $data): AnalyticsPeriod
    {
        $lifetimeStart = $this->capabilities($account)['lifetime_start'] ?? null;

        return AnalyticsPeriod::resolve(
            $data['date_range'] ?? 'last_28_days',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $lifetimeStart ? CarbonImmutable::parse($lifetimeStart) : null,
        );
    }

    public function dashboard(SocialAccount $account, AnalyticsPeriod $period, User $user, bool $refresh = false): array
    {
        $provider = $this->provider($account);
        $capabilities = $provider->capabilities();
        $cacheKey = "analytics:v1:dashboard:{$account->id}:{$period->dateRange}:{$period->start->toDateString()}:{$period->end->toDateString()}";

        if ($refresh) {
            $this->ensureCanRefresh($account, $user);
            Cache::forget($cacheKey);
        }

        // Exceções não são cacheadas: o Cache::remember só grava quando o callback retorna.
        return Cache::remember(
            $cacheKey,
            now()->addMinutes($capabilities['cache_minutes']),
            fn () => $this->buildDashboard($provider, $account, $period, $capabilities),
        );
    }

    public function bestTimes(SocialAccount $account): array
    {
        $cacheKey = "analytics:v1:best_times:{$account->id}";

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $provider = $this->provider($account);
            $contents = $this->callProvider($account, fn () => $provider->recentContents($account, $this->accessToken($account)));
        } catch (AnalyticsException) {
            // Melhores horários é uma sugestão: sem dados, devolve o padrão (sem cachear).
            return MetricsCalculator::bestHours([]);
        }

        $result = MetricsCalculator::bestHours($contents, $provider->capabilities()['best_times_metric']);
        Cache::put($cacheKey, $result, now()->addDays(self::BEST_TIMES_CACHE_DAYS));

        return $result;
    }

    /**
     * Métricas da conta para o snapshot diário (null = plataforma não precisa).
     */
    public function snapshotMetrics(SocialAccount $account): ?array
    {
        $provider = $this->provider($account);

        return $this->callProvider($account, fn () => $provider->snapshot($account, $this->accessToken($account)));
    }

    private function buildDashboard(AnalyticsProviderInterface $provider, SocialAccount $account, AnalyticsPeriod $period, array $capabilities): array
    {
        $sections = $this->callProvider($account, fn () => $provider->dashboard($account, $this->accessToken($account), $period));

        $benchmark = $capabilities['engagement_benchmark'];
        $viewsTrend = collect($sections['overview'])->firstWhere('key', 'views')['trend'] ?? null;
        $averageEngagement = collect($sections['top_content']['items'])->avg('metrics.engagement_rate');
        $daysSinceLastActivity = $account->daysSinceLastActivity();

        return [
            'platform' => $account->platform,
            'period' => $period->toArray(),
            'capabilities' => collect($capabilities)->only(['date_ranges', 'max_range_days', 'has_daily_series', 'has_revenue', 'has_watch_time'])->all(),
            'overview' => $sections['overview'],
            'time_series' => $sections['time_series'],
            'breakdowns' => $sections['breakdowns'],
            'top_content' => $sections['top_content'],
            'score' => MetricsCalculator::score(
                $averageEngagement === null ? null : (float) $averageEngagement,
                $benchmark,
                $viewsTrend,
                $daysSinceLastActivity,
            ),
            'alerts' => $this->alerts($sections, $benchmark, $viewsTrend, $daysSinceLastActivity),
            'notes' => $sections['notes'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Alertas a partir dos números reais do período.
     */
    private function alerts(array $sections, float $benchmark, ?float $viewsTrend, ?int $daysSinceLastActivity): array
    {
        $alerts = [];

        if ($viewsTrend !== null && $viewsTrend <= -self::VIEWS_ALERT_THRESHOLD) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Visualizações em queda',
                'message' => 'As visualizações caíram '.abs($viewsTrend).'% em relação ao período anterior.',
            ];
        }

        if ($viewsTrend !== null && $viewsTrend >= self::VIEWS_ALERT_THRESHOLD) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Visualizações em alta',
                'message' => "As visualizações subiram {$viewsTrend}% em relação ao período anterior. Continue nesse ritmo!",
            ];
        }

        $highlight = collect($sections['top_content']['items'])
            ->filter(fn (array $item) => ($item['metrics']['engagement_rate'] ?? 0) >= $benchmark * 1.5)
            ->sortByDesc('metrics.engagement_rate')
            ->first();

        if ($highlight) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Conteúdo em destaque',
                'message' => "\"{$highlight['title']}\" teve {$highlight['metrics']['engagement_rate']}% de engajamento, bem acima da média. Vale produzir mais nessa linha.",
            ];
        }

        $netFollowers = collect($sections['overview'])->firstWhere('key', 'net_followers')['value'] ?? null;

        if ($netFollowers !== null && $netFollowers < 0) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Perda de seguidores',
                'message' => 'A conta perdeu '.abs($netFollowers).' seguidores no período.',
            ];
        }

        if ($daysSinceLastActivity !== null && $daysSinceLastActivity >= self::STALE_ALERT_DAYS) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Conta sem postar',
                'message' => "Faz {$daysSinceLastActivity} dias desde a última postagem. A frequência pesa no alcance.",
            ];
        }

        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Tudo em ordem!',
                'message' => 'As métricas da conta parecem saudáveis neste período.',
            ];
        }

        return $alerts;
    }

    private function ensureCanRefresh(SocialAccount $account, User $user): void
    {
        $key = "analytics_refresh:{$account->id}:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw AnalyticsException::refreshTooSoon((int) ceil(RateLimiter::availableIn($key) / 60));
        }

        RateLimiter::hit($key, self::REFRESH_COOLDOWN_SECONDS);
    }

    /**
     * Token válido (renova se preciso). Falha na renovação = usuário precisa reconectar.
     */
    private function accessToken(SocialAccount $account): string
    {
        try {
            return $account->getValidToken();
        } catch (Throwable $e) {
            Log::warning("Analytics: falha ao renovar token da conta {$account->id}: {$e->getMessage()}");

            throw AnalyticsException::reauthRequired(Platform::from($account->platform));
        }
    }

    /**
     * Executa a chamada ao provider. Erro inesperado vira 502 genérico; o detalhe fica só no log.
     */
    private function callProvider(SocialAccount $account, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (AnalyticsException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Analytics: falha no provider {$account->platform} (conta {$account->id}): {$e->getMessage()}");

            throw AnalyticsException::providerUnavailable(Platform::from($account->platform));
        }
    }

    private function provider(SocialAccount $account): AnalyticsProviderInterface
    {
        return $this->factory->make(Platform::from($account->platform));
    }
}
