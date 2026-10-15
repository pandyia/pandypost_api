<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;

/**
 * Cálculos de analytics compartilhados entre as plataformas (funções puras).
 */
final class MetricsCalculator
{
    public const TIMEZONE = 'America/Sao_Paulo';

    public const FALLBACK_HOURS = [14, 18, 20];

    // Abaixo disso a média por hora não é confiável e usamos o fallback.
    private const MIN_BEST_HOURS_SAMPLE = 5;

    /**
     * Variação % entre períodos. Sem base de comparação (anterior nulo ou zero) → null.
     */
    public static function trend(int|float|null $current, int|float|null $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /**
     * Engajamento % = interações ÷ visualizações. Sem visualizações → null.
     */
    public static function engagementRate(int|float $interactions, int|float $views): ?float
    {
        if ($views <= 0) {
            return null;
        }

        return round(($interactions / $views) * 100, 2);
    }

    /**
     * Nota da conta (0–100), em três partes:
     * - engajamento (50): média do período vs. referência da plataforma (teto em 2× a referência);
     * - crescimento (30): variação de visualizações, de −50% (0 pt) a +50% (30 pt); sem comparação = 15;
     * - frequência (20): dias desde a última postagem/agendamento.
     */
    public static function score(?float $averageEngagement, float $benchmark, ?float $viewsTrend, ?int $daysSinceLastActivity): array
    {
        $engagement = $averageEngagement === null
            ? 0
            : (int) round(min(1, $averageEngagement / ($benchmark * 2)) * 50);

        $growth = $viewsTrend === null
            ? 15
            : (int) round((max(-50, min(50, $viewsTrend)) + 50) / 100 * 30);

        $frequency = match (true) {
            $daysSinceLastActivity === null => 0,
            $daysSinceLastActivity <= 3 => 20,
            $daysSinceLastActivity <= 7 => 13,
            $daysSinceLastActivity <= 15 => 6,
            default => 0,
        };

        return [
            'value' => $engagement + $growth + $frequency,
            'engagement' => $engagement,
            'growth' => $growth,
            'frequency' => $frequency,
        ];
    }

    /**
     * Melhores horários para postar: média de visualizações por hora de publicação.
     *
     * @param  array<int, array{published_at: CarbonImmutable, value: int|float}>  $contents
     */
    public static function bestHours(array $contents, string $metric = 'views'): array
    {
        $byHour = array_fill(0, 24, ['total' => 0, 'posts' => 0]);

        foreach ($contents as $content) {
            $hour = (int) $content['published_at']->setTimezone(self::TIMEZONE)->format('G');
            $byHour[$hour]['total'] += $content['value'];
            $byHour[$hour]['posts']++;
        }

        $averages = collect($byHour)->map(fn (array $slot, int $hour) => [
            'hour' => $hour,
            'average' => $slot['posts'] > 0 ? round($slot['total'] / $slot['posts'], 1) : 0,
            'posts' => $slot['posts'],
        ]);

        $sampleSize = count($contents);
        $isFallback = $sampleSize < self::MIN_BEST_HOURS_SAMPLE;

        $bestHours = $isFallback
            ? self::FALLBACK_HOURS
            : $averages->where('posts', '>', 0)->sortByDesc('average')->take(3)->pluck('hour')->values()->all();

        return [
            'best_hours' => $bestHours,
            'by_hour' => $averages->values()->all(),
            'metric' => $metric,
            'sample_size' => $sampleSize,
            'is_fallback' => $isFallback,
            'timezone' => self::TIMEZONE,
        ];
    }

    /**
     * Item da visão geral no formato do contrato (valor, anterior e variação).
     */
    public static function overviewItem(string $key, string $label, string $format, int|float|null $value, int|float|null $previous, ?string $description = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'format' => $format,
            'value' => $value,
            'previous' => $previous,
            'trend' => self::trend($value, $previous),
            'description' => $description,
        ];
    }
}
