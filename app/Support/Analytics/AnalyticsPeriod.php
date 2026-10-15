<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Período de análise (atual + anterior de mesmo tamanho, para comparação).
 *
 * Datas são inclusivas: "last_7_days" = hoje e os 6 dias anteriores.
 */
final class AnalyticsPeriod
{
    public const PRESETS = [
        'last_7_days',
        'last_28_days',
        'last_90_days',
        'this_month',
        'last_month',
        'last_365_days',
        'lifetime',
        'custom',
    ];

    // Acima disso a série passa a ser mensal, para não gerar centenas de pontos.
    private const MONTHLY_THRESHOLD_DAYS = 400;

    public function __construct(
        public readonly string $dateRange,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly ?CarbonImmutable $previousStart,
        public readonly ?CarbonImmutable $previousEnd,
    ) {}

    /**
     * Monta o período a partir do preset (ou das datas, quando custom).
     * $lifetimeStart: desde quando a plataforma tem dado (usado em "lifetime").
     */
    public static function resolve(
        string $dateRange,
        ?string $customStart = null,
        ?string $customEnd = null,
        ?CarbonImmutable $lifetimeStart = null,
    ): self {
        $today = CarbonImmutable::today();

        return match ($dateRange) {
            'last_28_days' => self::lastDays($dateRange, 28, $today),
            'last_90_days' => self::lastDays($dateRange, 90, $today),
            'last_365_days' => self::lastDays($dateRange, 365, $today),
            'this_month' => self::sameLength($dateRange, $today->startOfMonth(), $today),
            'last_month' => self::lastMonth($today),
            'lifetime' => new self($dateRange, $lifetimeStart ?? $today->subYears(20), $today, null, null),
            'custom' => self::sameLength(
                $dateRange,
                CarbonImmutable::parse($customStart)->startOfDay(),
                CarbonImmutable::parse($customEnd)->startOfDay(),
            ),
            default => self::lastDays('last_7_days', 7, $today),
        };
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    public function hasPrevious(): bool
    {
        return $this->previousStart !== null;
    }

    public function granularity(): string
    {
        return $this->days() > self::MONTHLY_THRESHOLD_DAYS ? 'month' : 'day';
    }

    /**
     * Rótulos de cada ponto da série atual ('Y-m-d' ou 'Y-m').
     *
     * @return string[]
     */
    public function buckets(): array
    {
        return self::bucketsBetween($this->start, $this->end, $this->granularity());
    }

    /**
     * Mesmos pontos, no período anterior (alinhados por posição com buckets()).
     *
     * @return string[]
     */
    public function previousBuckets(): array
    {
        if (! $this->hasPrevious()) {
            return [];
        }

        return self::bucketsBetween($this->previousStart, $this->previousEnd, $this->granularity());
    }

    public function contains(CarbonImmutable $moment): bool
    {
        return $moment->betweenIncluded($this->start->startOfDay(), $this->end->endOfDay());
    }

    public function containsPrevious(CarbonImmutable $moment): bool
    {
        return $this->hasPrevious()
            && $moment->betweenIncluded($this->previousStart->startOfDay(), $this->previousEnd->endOfDay());
    }

    public function toArray(): array
    {
        return [
            'date_range' => $this->dateRange,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'previous_start' => $this->previousStart?->toDateString(),
            'previous_end' => $this->previousEnd?->toDateString(),
            'granularity' => $this->granularity(),
        ];
    }

    private static function lastDays(string $dateRange, int $days, CarbonImmutable $today): self
    {
        return self::sameLength($dateRange, $today->subDays($days - 1), $today);
    }

    /**
     * O período anterior tem o mesmo número de dias e termina no dia anterior ao início.
     */
    private static function sameLength(string $dateRange, CarbonImmutable $start, CarbonImmutable $end): self
    {
        $days = (int) $start->diffInDays($end);
        $previousEnd = $start->subDay();

        return new self($dateRange, $start, $end, $previousEnd->subDays($days), $previousEnd);
    }

    private static function lastMonth(CarbonImmutable $today): self
    {
        $start = $today->subMonthNoOverflow()->startOfMonth();
        $previousStart = $start->subMonthNoOverflow()->startOfMonth();

        return new self('last_month', $start, $start->endOfMonth()->startOfDay(), $previousStart, $previousStart->endOfMonth()->startOfDay());
    }

    private static function bucketsBetween(CarbonImmutable $start, CarbonImmutable $end, string $granularity): array
    {
        if ($granularity === 'month') {
            return collect(CarbonPeriod::create($start->startOfMonth(), '1 month', $end->startOfMonth()))
                ->map(fn ($date) => $date->format('Y-m'))
                ->all();
        }

        return collect(CarbonPeriod::create($start, '1 day', $end))
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->all();
    }
}
