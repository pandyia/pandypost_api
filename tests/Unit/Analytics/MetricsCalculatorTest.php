<?php

use App\Support\Analytics\MetricsCalculator;
use Carbon\CarbonImmutable;

describe('MetricsCalculator::trend', function () {

    it('calcula a variação percentual', function () {
        expect(MetricsCalculator::trend(150, 100))->toBe(50.0)
            ->and(MetricsCalculator::trend(50, 100))->toBe(-50.0);
    });

    it('retorna null quando não há base de comparação', function () {
        expect(MetricsCalculator::trend(100, 0))->toBeNull()
            ->and(MetricsCalculator::trend(100, null))->toBeNull()
            ->and(MetricsCalculator::trend(null, 10))->toBeNull();
    });

});

describe('MetricsCalculator::engagementRate', function () {

    it('divide interações por visualizações em %', function () {
        expect(MetricsCalculator::engagementRate(45, 1000))->toBe(4.5);
    });

    it('retorna null sem visualizações', function () {
        expect(MetricsCalculator::engagementRate(10, 0))->toBeNull();
    });

});

describe('MetricsCalculator::score', function () {

    it('soma engajamento, crescimento e frequência', function () {
        // Engajamento 4% com referência 4% = metade do teto (25); +50% de views = 30; postou há 2 dias = 20.
        expect(MetricsCalculator::score(4.0, 4.0, 50.0, 2))
            ->toBe(['value' => 75, 'engagement' => 25, 'growth' => 30, 'frequency' => 20]);
    });

    it('limita cada parte ao seu teto e trata ausência de dados', function () {
        expect(MetricsCalculator::score(100.0, 4.0, 900.0, 1)['value'])->toBe(100)
            ->and(MetricsCalculator::score(null, 4.0, null, null))
            ->toBe(['value' => 15, 'engagement' => 0, 'growth' => 15, 'frequency' => 0])
            ->and(MetricsCalculator::score(null, 4.0, -80.0, 30)['growth'])->toBe(0);
    });

});

describe('MetricsCalculator::bestHours', function () {

    it('ordena as horas pela média de visualizações no fuso de São Paulo', function () {
        // 21:00 UTC = 18:00 em São Paulo; 15:00 UTC = 12:00.
        $contents = [
            ['published_at' => CarbonImmutable::parse('2026-10-01 21:00:00', 'UTC'), 'value' => 1000],
            ['published_at' => CarbonImmutable::parse('2026-10-02 21:30:00', 'UTC'), 'value' => 800],
            ['published_at' => CarbonImmutable::parse('2026-10-03 15:00:00', 'UTC'), 'value' => 100],
            ['published_at' => CarbonImmutable::parse('2026-10-04 23:00:00', 'UTC'), 'value' => 500],
            ['published_at' => CarbonImmutable::parse('2026-10-05 12:00:00', 'UTC'), 'value' => 50],
        ];

        $result = MetricsCalculator::bestHours($contents);

        expect($result['best_hours'])->toBe([18, 20, 12])
            ->and($result['is_fallback'])->toBeFalse()
            ->and($result['sample_size'])->toBe(5)
            ->and($result['by_hour'])->toHaveCount(24)
            ->and($result['by_hour'][18])->toBe(['hour' => 18, 'average' => 900.0, 'posts' => 2]);
    });

    it('usa o fallback com menos de 5 conteúdos', function () {
        $result = MetricsCalculator::bestHours([
            ['published_at' => CarbonImmutable::parse('2026-10-01 21:00:00', 'UTC'), 'value' => 1000],
        ]);

        expect($result['best_hours'])->toBe(MetricsCalculator::FALLBACK_HOURS)
            ->and($result['is_fallback'])->toBeTrue();
    });

});
