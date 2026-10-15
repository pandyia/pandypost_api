<?php

use App\Support\Analytics\AnalyticsPeriod;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

beforeEach(fn () => Carbon::setTestNow('2026-10-14 15:00:00'));
afterEach(fn () => Carbon::setTestNow());

describe('AnalyticsPeriod', function () {

    it('last_7_days cobre hoje e os 6 dias anteriores, com período anterior de mesmo tamanho', function () {
        $period = AnalyticsPeriod::resolve('last_7_days');

        expect($period->toArray())->toMatchArray([
            'start' => '2026-10-08',
            'end' => '2026-10-14',
            'previous_start' => '2026-10-01',
            'previous_end' => '2026-10-07',
            'granularity' => 'day',
        ])->and($period->days())->toBe(7)
            ->and($period->buckets())->toHaveCount(7)
            ->and($period->previousBuckets())->toHaveCount(7);
    });

    it('custom usa as datas informadas e compara com o mesmo número de dias antes', function () {
        $period = AnalyticsPeriod::resolve('custom', '2026-09-01', '2026-09-10');

        expect($period->days())->toBe(10)
            ->and($period->previousStart->toDateString())->toBe('2026-08-22')
            ->and($period->previousEnd->toDateString())->toBe('2026-08-31');
    });

    it('last_month compara com o mês cheio anterior', function () {
        $period = AnalyticsPeriod::resolve('last_month');

        expect($period->toArray())->toMatchArray([
            'start' => '2026-09-01',
            'end' => '2026-09-30',
            'previous_start' => '2026-08-01',
            'previous_end' => '2026-08-31',
        ]);
    });

    it('lifetime não tem período anterior e fica mensal quando longo', function () {
        $period = AnalyticsPeriod::resolve('lifetime', lifetimeStart: CarbonImmutable::parse('2020-01-15'));

        expect($period->hasPrevious())->toBeFalse()
            ->and($period->granularity())->toBe('month')
            ->and($period->buckets()[0])->toBe('2020-01')
            ->and($period->previousBuckets())->toBe([]);
    });

    it('preset desconhecido cai em last_7_days', function () {
        expect(AnalyticsPeriod::resolve('qualquer')->dateRange)->toBe('last_7_days');
    });

    it('identifica se um momento está no período atual ou no anterior', function () {
        $period = AnalyticsPeriod::resolve('last_7_days');

        expect($period->contains(CarbonImmutable::parse('2026-10-14 23:59:00')))->toBeTrue()
            ->and($period->contains(CarbonImmutable::parse('2026-10-07 10:00:00')))->toBeFalse()
            ->and($period->containsPrevious(CarbonImmutable::parse('2026-10-07 10:00:00')))->toBeTrue();
    });

});
