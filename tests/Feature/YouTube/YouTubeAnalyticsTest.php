<?php

use App\Services\YouTubeAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Carbon::setTestNow('2026-10-01 12:00:00');

    $this->user = createUserWithPermissions();
    $this->account = createSocialAccount('youtube', $this->user);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Responde as consultas do YouTube Analytics e da Data API com um canal de exemplo.
 * O período atual (last_7_days) começa em 2026-09-24; o anterior, em 2026-09-17.
 */
function fakeYouTubeChannel(): ArrayObject
{
    return fakeGoogleApi(function (RequestInterface $request) {
        parse_str($request->getUri()->getQuery(), $query);
        $path = $request->getUri()->getPath();
        $isCurrentPeriod = ($query['startDate'] ?? null) === '2026-09-24';

        if (str_contains($path, '/v2/reports')) {
            return googleResponse(['rows' => match ($query['dimensions'] ?? null) {
                null => $isCurrentPeriod ? [[1200, 600, 30, 5, 150, 12.5]] : [[1000, 500, 20, 4, 140, 10.0]],
                'day' => $isCurrentPeriod ? [['2026-09-24', 200, 100], ['2026-09-25', 300, 150]] : [['2026-09-17', 150, 80]],
                'video' => [['vid_a', 800, 400, 90, 6, 0.0], ['vid_b', 400, 200, 30, 1, 0.0]],
                'insightTrafficSourceType' => [['YT_SEARCH', 700], ['RELATED_VIDEO', 500]],
            }]);
        }

        if (str_contains($path, '/youtube/v3/videos') && $query['part'] === 'snippet,contentDetails,statistics') {
            return googleResponse(['items' => [
                youTubeVideoItem('vid_a', 'Vídeo campeão', 'PT3M', 5000),
                youTubeVideoItem('vid_b', 'Short rápido', 'PT45S', 900),
            ]]);
        }

        if (str_contains($path, '/youtube/v3/channels')) {
            return googleResponse(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU_uploads']]]]]);
        }

        if (str_contains($path, '/youtube/v3/playlistItems')) {
            return googleResponse(['items' => array_map(
                fn (string $id) => ['snippet' => ['resourceId' => ['videoId' => $id]]],
                ['v1', 'v2', 'v3', 'v4'],
            )]);
        }

        // videos.list de best-times: horários em UTC (São Paulo = UTC-3).
        return googleResponse(['items' => [
            ['id' => 'v1', 'snippet' => ['publishedAt' => '2026-09-01T21:00:00Z'], 'statistics' => ['viewCount' => '9000']],
            ['id' => 'v2', 'snippet' => ['publishedAt' => '2026-09-02T21:30:00Z'], 'statistics' => ['viewCount' => '7000']],
            ['id' => 'v3', 'snippet' => ['publishedAt' => '2026-09-03T15:00:00Z'], 'statistics' => ['viewCount' => '3000']],
            ['id' => 'v4', 'snippet' => ['publishedAt' => '2026-09-04T11:00:00Z'], 'statistics' => ['viewCount' => '500']],
        ]]);
    });
}

function youTubeVideoItem(string $id, string $title, string $duration, int $views): array
{
    return [
        'id' => $id,
        'snippet' => ['title' => $title, 'thumbnails' => ['high' => ['url' => "https://i.ytimg.com/{$id}.jpg"]]],
        'contentDetails' => ['duration' => $duration],
        'statistics' => ['viewCount' => (string) $views, 'likeCount' => '50', 'commentCount' => '7'],
    ];
}

describe('dashboard de analytics', function () {

    it('monta o dashboard do canal no formato esperado pelo front', function () {
        fakeYouTubeChannel();

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertOk();

        expect($response->json())->toMatchSnapshot();
    });

    it('guarda o dashboard em cache e só consulta o Google de novo com refresh', function () {
        $requests = fakeYouTubeChannel();
        $url = "/api/analytics/{$this->account->uuid}/dashboard";

        $this->withToken($this->user->test_token)->getJson($url)->assertOk();
        $afterFirstLoad = count($requests);

        $this->withToken($this->user->test_token)->getJson($url)->assertOk();
        expect(count($requests))->toBe($afterFirstLoad);

        $this->withToken($this->user->test_token)->getJson("{$url}?refresh=1")->assertOk();
        expect(count($requests))->toBeGreaterThan($afterFirstLoad);
    });

    it('limita a atualização manual a uma a cada 10 minutos', function () {
        fakeYouTubeChannel();
        $url = "/api/analytics/{$this->account->uuid}/dashboard?refresh=1";

        $this->withToken($this->user->test_token)->getJson($url)->assertOk();

        $this->withToken($this->user->test_token)->getJson($url)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Você já atualizou os dados recentemente. Tente novamente em 10 minutos.');
    });

    it('pede nova autorização quando o Google recusa a permissão', function () {
        fakeGoogleApi(fn () => googleResponse(['error' => ['code' => 403, 'message' => 'Insufficient permission']], 403));

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertStatus(401)
            ->assertJsonPath('requires_reauth', true);
    });

    it('responde 500 sem detalhes técnicos quando o Google falha', function () {
        config(['app.debug' => false]);
        $this->mock(YouTubeAnalyticsService::class)
            ->shouldReceive('getDashboardData')->andThrow(new RuntimeException('Backend Error'));

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Falha ao carregar métricas do YouTube. Tente novamente mais tarde.']);
    });

    it('inclui o erro técnico só com APP_DEBUG ligado', function () {
        config(['app.debug' => true]);
        $this->mock(YouTubeAnalyticsService::class)
            ->shouldReceive('getDashboardData')->andThrow(new RuntimeException('Backend Error'));

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertStatus(500)
            ->assertJsonPath('error', 'Backend Error');
    });

    it('recusa contas que não são do YouTube', function () {
        $tiktok = createSocialAccount('tiktok', $this->user);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$tiktok->uuid}/dashboard")
            ->assertStatus(400);
    });

    it('não mostra analytics de conta de outro workspace', function () {
        $intruder = createUserWithPermissions();

        $this->withToken($intruder->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard")
            ->assertNotFound();
    });
});

describe('melhores horários para postar', function () {

    it('devolve as 3 horas com mais views em média, no fuso de São Paulo', function () {
        fakeYouTubeChannel();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/best-times")
            ->assertOk()
            ->assertExactJson(['best_hours' => [18, 12, 8]]);
    });

    it('devolve o horário padrão para contas que não são do YouTube', function () {
        $tiktok = createSocialAccount('tiktok', $this->user);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$tiktok->uuid}/best-times")
            ->assertExactJson(['best_hours' => [14, 18, 20]]);
    });

    it('devolve o horário padrão quando o Google falha, sem guardá-lo em cache', function () {
        fakeGoogleApi(fn () => googleResponse(['error' => ['code' => 500]], 500));
        $url = "/api/analytics/{$this->account->uuid}/best-times";

        $this->withToken($this->user->test_token)->getJson($url)->assertExactJson(['best_hours' => [14, 18, 20]]);

        fakeYouTubeChannel();
        $this->withToken($this->user->test_token)->getJson($url)->assertExactJson(['best_hours' => [18, 12, 8]]);
    });

    it('guarda os horários calculados em cache', function () {
        $requests = fakeYouTubeChannel();
        $url = "/api/analytics/{$this->account->uuid}/best-times";

        $this->withToken($this->user->test_token)->getJson($url);
        $afterFirstLoad = count($requests);
        $this->withToken($this->user->test_token)->getJson($url)->assertExactJson(['best_hours' => [18, 12, 8]]);

        expect(count($requests))->toBe($afterFirstLoad);
    });
});
