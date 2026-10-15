<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Carbon::setTestNow('2026-10-14 12:00:00');
    $this->user = createUserWithPermissions([]);
    $this->account = connectedSocialAccount($this->user, 'youtube');
});

afterEach(fn () => Carbon::setTestNow());

function youtubeReport(array $columns, array $rows): array
{
    return [
        'columnHeaders' => array_map(fn ($name) => ['name' => $name], $columns),
        'rows' => $rows,
    ];
}

/**
 * Responde a YouTube Analytics/Data API conforme a consulta (overview, série, ranking, quebra).
 */
function fakeYouTube(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        if (str_contains($url, 'youtube/v3/videos')) {
            return Http::response(['items' => [
                [
                    'id' => 'vid1',
                    'snippet' => ['title' => 'Vídeo campeão', 'publishedAt' => '2026-10-01T21:00:00Z', 'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/vid1.jpg']]],
                    'contentDetails' => ['duration' => 'PT2M30S'],
                    'statistics' => ['viewCount' => '5000'],
                ],
                [
                    'id' => 'vid2',
                    'snippet' => ['title' => 'Vídeo longo', 'publishedAt' => '2026-09-20T15:00:00Z', 'thumbnails' => ['default' => ['url' => 'https://i.ytimg.com/vid2.jpg']]],
                    'contentDetails' => ['duration' => 'PT12M'],
                    'statistics' => ['viewCount' => '300'],
                ],
            ]]);
        }

        $dimensions = $query['dimensions'] ?? null;
        $isCurrent = ($query['startDate'] ?? '') === '2026-10-08';

        return match ($dimensions) {
            null => Http::response(youtubeReport(
                ['views', 'estimatedMinutesWatched', 'averageViewDuration', 'averageViewPercentage', 'subscribersGained', 'subscribersLost', 'likes', 'comments', 'shares'],
                $isCurrent ? [[1500, 600, 95, 42.5, 30, 5, 80, 15, 5]] : [[1000, 400, 90, 40.0, 10, 2, 40, 5, 5]],
            )),
            'day' => Http::response(youtubeReport(
                ['day', 'views', 'estimatedMinutesWatched', 'subscribersGained', 'subscribersLost', 'likes', 'comments', 'shares'],
                $isCurrent ? [['2026-10-08', 700, 300, 10, 1, 40, 5, 2], ['2026-10-10', 800, 300, 20, 4, 40, 10, 3]] : [['2026-10-01', 1000, 400, 10, 2, 40, 5, 5]],
            )),
            'video' => Http::response(youtubeReport(
                ['video', 'views', 'estimatedMinutesWatched', 'averageViewDuration', 'averageViewPercentage', 'likes', 'comments', 'shares', 'subscribersGained'],
                [['vid1', 1000, 300, 90, 60.0, 70, 10, 20, 25], ['vid2', 500, 300, 360, 30.0, 10, 5, 0, 5]],
            )),
            'insightTrafficSourceType' => Http::response(youtubeReport(['insightTrafficSourceType', 'views'], [['YT_SEARCH', 900], ['SHORTS', 600]])),
            // Dimensão não aceita para o canal: a seção deve ser omitida, sem quebrar o dashboard.
            'creatorContentType' => Http::response(['error' => ['code' => 400, 'message' => 'Unknown dimension']], 400),
        };
    });
}

describe('dashboard do YouTube', function () {

    it('retorna o contrato normalizado com métricas reais', function () {
        fakeYouTube();

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk()
            ->assertJsonPath('data.platform', 'youtube')
            ->assertJsonPath('data.period.start', '2026-10-08')
            ->assertJsonPath('data.period.previous_end', '2026-10-07')
            ->assertJsonPath('data.capabilities.has_revenue', false)
            ->assertJsonStructure(['data' => [
                'overview' => [['key', 'label', 'format', 'value', 'previous', 'trend']],
                'time_series' => ['dates', 'series' => [['key', 'label', 'format', 'type', 'current', 'previous']]],
                'breakdowns', 'top_content' => ['columns', 'items'], 'score' => ['value', 'engagement', 'growth', 'frequency'],
                'alerts', 'notes', 'generated_at',
            ]]);

        $overview = collect($response->json('data.overview'))->keyBy('key');

        expect($overview['views'])->toMatchArray(['value' => 1500, 'previous' => 1000, 'trend' => 50])
            ->and($overview['watch_time']['value'])->toBe(10)
            ->and($overview['net_followers']['value'])->toBe(25)
            ->and($overview['engagement_rate']['value'])->toBe(6.67)
            ->and($overview['average_view_percentage']['value'])->toBe(42.5)
            ->and($overview->keys()->all())->not->toContain('ctr', 'estimated_revenue');
    });

    it('preenche a série com zero nos dias sem dado e alinha o período anterior', function () {
        fakeYouTube();

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk();

        $views = collect($response->json('data.time_series.series'))->firstWhere('key', 'views');

        expect($response->json('data.time_series.dates'))->toHaveCount(7)
            ->and($views['current'])->toBe([700, 0, 800, 0, 0, 0, 0])
            ->and($views['previous'])->toBe([1000, 0, 0, 0, 0, 0, 0]);
    });

    it('classifica Shorts por duração de até 3 minutos e calcula o engajamento por vídeo', function () {
        fakeYouTube();

        $items = collect($this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->json('data.top_content.items'))->keyBy('id');

        expect($items['vid1'])->toMatchArray(['title' => 'Vídeo campeão', 'format' => 'short', 'permalink' => 'https://www.youtube.com/shorts/vid1'])
            ->and($items['vid1']['metrics']['engagement_rate'])->toBe(10)
            ->and($items['vid1']['metrics']['average_view_percentage'])->toBe(60)
            ->and($items['vid2']['format'])->toBe('video');
    });

    it('omite a quebra que a API não aceita e mantém as demais', function () {
        fakeYouTube();

        $breakdowns = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->json('data.breakdowns');

        expect($breakdowns)->toHaveCount(1)
            ->and($breakdowns[0]['key'])->toBe('traffic_sources')
            ->and($breakdowns[0]['items'][1]['label'])->toBe('Feed de Shorts');
    });

    it('gera alerta de alta e de conteúdo em destaque a partir dos dados', function () {
        fakeYouTube();

        $titles = collect($this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->json('data.alerts'))->pluck('title');

        expect($titles)->toContain('Visualizações em alta', 'Conteúdo em destaque');
    });

    it('lifetime não compara com período anterior', function () {
        Http::fake(['*' => Http::response(youtubeReport(['views'], []))]);

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=lifetime")
            ->assertOk();

        $views = collect($response->json('data.overview'))->firstWhere('key', 'views');

        expect($response->json('data.period.previous_start'))->toBeNull()
            ->and($views['previous'])->toBeNull()
            ->and($views['trend'])->toBeNull()
            ->and($response->json('data.period.granularity'))->toBe('month');
    });

});

describe('erros do dashboard', function () {

    it('retorna 409 analytics_reauth_required quando o Google nega a permissão', function () {
        Http::fake(['*' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'insufficientPermissions']]]], 403)]);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertStatus(409)
            ->assertJsonPath('error', 'analytics_reauth_required');
    });

    it('retorna 409 quando o token não pode ser renovado', function () {
        $this->account->update(['expires_at' => now()->subHour(), 'refresh_token' => null]);
        Http::fake();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertStatus(409)
            ->assertJsonPath('error', 'analytics_reauth_required');
    });

    it('retorna 502 genérico sem vazar detalhes e não guarda o erro no cache', function () {
        // Enquanto $isDown, o Google responde 500; depois, cai no fake normal.
        $isDown = true;
        Http::fake(function () use (&$isDown) {
            return $isDown ? Http::response('segredo-interno do backend do Google', 500) : null;
        });
        fakeYouTube();

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertStatus(502)
            ->assertJsonPath('error', 'analytics_provider_unavailable');

        expect($response->getContent())->not->toContain('segredo-interno');

        // A próxima chamada ao MESMO período vai de novo à API (o erro não foi cacheado).
        $isDown = false;

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertOk();
    });

    it('cota estourada é indisponibilidade, não reconexão', function () {
        Http::fake(['*' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403)]);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertStatus(502);
    });

    it('retorna 422 para período inválido ou datas incoerentes', function () {
        Http::fake();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=ontem")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_range']);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=custom&start_date=2026-10-10&end_date=2026-10-01")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=custom&start_date=2026-10-01&end_date=2026-10-20")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        Http::assertNothingSent();
    });

    it('limita a atualização manual a uma vez a cada 10 minutos', function () {
        fakeYouTube();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days&refresh=1")
            ->assertOk();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days&refresh=1")
            ->assertStatus(429)
            ->assertJsonPath('error', 'analytics_refresh_too_soon');
    });

    it('usa o cache no segundo acesso ao mesmo período', function () {
        fakeYouTube();

        $this->withToken($this->user->test_token)->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")->assertOk();
        $calls = count(Http::recorded());

        $this->withToken($this->user->test_token)->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")->assertOk();

        expect(count(Http::recorded()))->toBe($calls);
    });

    it('retorna 404 para conta de outro workspace', function () {
        $foreign = connectedSocialAccount(createUserWithPermissions([]), 'youtube');
        Http::fake();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$foreign->uuid}/dashboard")
            ->assertNotFound();

        Http::assertNothingSent();
    });

});
