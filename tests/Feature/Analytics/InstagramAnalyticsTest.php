<?php

use App\Models\SocialAccountMetricSnapshot;
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
    $this->account = connectedSocialAccount($this->user, 'instagram', ['platform_id' => '1789']);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Responde a Graph API do Instagram conforme o endpoint e os parâmetros.
 * $followsBreakdown = false simula conta com menos de 100 seguidores.
 */
function fakeInstagram(bool $followsBreakdown = true): void
{
    $currentSince = Carbon::parse('2026-10-08')->startOfDay()->getTimestamp();

    Http::fake(function (Request $request) use ($followsBreakdown, $currentSince) {
        $url = $request->url();
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $isCurrent = (int) ($query['since'] ?? 0) === $currentSince;
        $path = parse_url($url, PHP_URL_PATH);

        if ($path === '/v25.0/1789') {
            return Http::response(['followers_count' => 1250, 'follows_count' => 80, 'media_count' => 42]);
        }

        if ($path === '/v25.0/1789/media') {
            return Http::response(['data' => [
                ['id' => 'm1', 'caption' => "Reel que bombou\n#tags", 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'permalink' => 'https://instagram.com/reel/m1', 'thumbnail_url' => 'https://cdn/m1.jpg', 'timestamp' => '2026-10-10T20:00:00+0000', 'like_count' => 300, 'comments_count' => 40],
                ['id' => 'm2', 'caption' => null, 'media_type' => 'IMAGE', 'media_product_type' => 'FEED', 'permalink' => 'https://instagram.com/p/m2', 'media_url' => 'https://cdn/m2.jpg', 'timestamp' => '2026-10-09T12:00:00+0000', 'like_count' => 50, 'comments_count' => 5],
                ['id' => 'm3', 'caption' => 'Post antigo', 'media_type' => 'IMAGE', 'media_product_type' => 'FEED', 'timestamp' => '2026-09-01T12:00:00+0000', 'like_count' => 999, 'comments_count' => 99],
            ]]);
        }

        if ($path === '/v25.0/m1/insights') {
            return Http::response(['data' => [
                ['name' => 'views', 'values' => [['value' => 5000]]],
                ['name' => 'reach', 'values' => [['value' => 3200]]],
                ['name' => 'likes', 'values' => [['value' => 300]]],
                ['name' => 'comments', 'values' => [['value' => 40]]],
                ['name' => 'shares', 'values' => [['value' => 60]]],
                ['name' => 'saved', 'values' => [['value' => 100]]],
                ['name' => 'ig_reels_avg_watch_time', 'values' => [['value' => 7400]]],
            ]]);
        }

        if ($path === '/v25.0/m2/insights') {
            // Mídia sem suporte a insights: o post aparece com os números públicos.
            return Http::response(['error' => ['code' => 100, 'message' => 'Unsupported']], 400);
        }

        // Insights da conta.
        if (($query['metric'] ?? null) === 'follows_and_unfollows') {
            if (! $followsBreakdown) {
                return Http::response(['data' => [['name' => 'follows_and_unfollows', 'total_value' => []]]]);
            }

            return Http::response(['data' => [['name' => 'follows_and_unfollows', 'total_value' => ['breakdowns' => [['results' => [
                ['dimension_values' => ['FOLLOWER'], 'value' => $isCurrent ? 120 : 60],
                ['dimension_values' => ['NON_FOLLOWER'], 'value' => $isCurrent ? 20 : 10],
            ]]]]]]]);
        }

        if (($query['breakdown'] ?? null) === 'media_product_type') {
            return Http::response(['data' => [['name' => 'views', 'total_value' => ['breakdowns' => [['results' => [
                ['dimension_values' => ['REELS'], 'value' => 7000],
                ['dimension_values' => ['FEED'], 'value' => 3000],
            ]]]]]]]);
        }

        if (($query['metric_type'] ?? null) === 'time_series') {
            return Http::response(['data' => [['name' => 'reach', 'values' => $isCurrent
                ? [['value' => 400, 'end_time' => '2026-10-09T07:00:00+0000'], ['value' => 900, 'end_time' => '2026-10-11T07:00:00+0000']]
                : [['value' => 300, 'end_time' => '2026-10-02T07:00:00+0000']],
            ]]]);
        }

        $totals = $isCurrent
            ? ['views' => 10000, 'reach' => 6000, 'accounts_engaged' => 700, 'total_interactions' => 800, 'likes' => 500, 'comments' => 80, 'shares' => 120, 'saves' => 100, 'profile_links_taps' => 30]
            : ['views' => 8000, 'reach' => 5000, 'accounts_engaged' => 500, 'total_interactions' => 400, 'likes' => 250, 'comments' => 50, 'shares' => 60, 'saves' => 40, 'profile_links_taps' => 0];

        return Http::response(['data' => collect($totals)->map(fn ($value, $name) => ['name' => $name, 'total_value' => ['value' => $value]])->values()->all()]);
    });
}

describe('dashboard do Instagram', function () {

    it('retorna visão geral com views, alcance, engajamento e seguidores', function () {
        fakeInstagram();

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk()
            ->assertJsonPath('data.platform', 'instagram')
            ->assertJsonPath('data.capabilities.max_range_days', 30);

        $overview = collect($response->json('data.overview'))->keyBy('key');

        expect($overview['views'])->toMatchArray(['value' => 10000, 'previous' => 8000, 'trend' => 25])
            ->and($overview['reach']['value'])->toBe(6000)
            ->and($overview['engagement_rate']['value'])->toBe(8)
            ->and($overview['net_followers'])->toMatchArray(['value' => 100, 'previous' => 50, 'trend' => 100])
            ->and($overview['followers']['value'])->toBe(1250)
            ->and($overview['profile_links_taps']['trend'])->toBeNull()
            ->and($overview->keys())->not->toContain('impressions');
    });

    it('usa o token como parâmetro access_token, como o restante da integração', function () {
        fakeInstagram();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'access_token=token-valido'));
    });

    it('monta a série diária de alcance pelo dia medido', function () {
        fakeInstagram();

        $reach = collect($this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->json('data.time_series.series'))->firstWhere('key', 'reach');

        expect($reach['current'])->toBe([400, 0, 900, 0, 0, 0, 0])
            ->and($reach['previous'][0])->toBe(300);
    });

    it('lista só os posts do período, com insights e engajamento', function () {
        fakeInstagram();

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk();

        $items = collect($response->json('data.top_content.items'))->keyBy('id');

        expect($items->keys()->all())->toBe(['m1', 'm2'])
            ->and($items['m1'])->toMatchArray(['title' => 'Reel que bombou', 'format' => 'reel'])
            ->and($items['m1']['metrics'])->toMatchArray(['views' => 5000, 'reach' => 3200, 'saved' => 100, 'engagement_rate' => 10, 'average_watch_time' => 7.4])
            ->and($items['m2'])->toMatchArray(['title' => 'Post sem legenda', 'thumbnail_url' => 'https://cdn/m2.jpg'])
            ->and($items['m2']['metrics']['likes'])->toBe(50)
            ->and($items['m2']['metrics']['engagement_rate'])->toBeNull();

        expect($response->json('data.breakdowns.0.items.0'))->toBe(['key' => 'REELS', 'label' => 'Reels', 'value' => 7000]);
    });

    it('usa os snapshots para seguidores quando a conta tem menos de 100 seguidores', function () {
        fakeInstagram(followsBreakdown: false);

        SocialAccountMetricSnapshot::create(['social_account_id' => $this->account->id, 'date' => '2026-10-07', 'followers' => 1200]);
        SocialAccountMetricSnapshot::create(['social_account_id' => $this->account->id, 'date' => '2026-10-10', 'followers' => 1230]);

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk();

        $overview = collect($response->json('data.overview'))->keyBy('key');
        $followers = collect($response->json('data.time_series.series'))->firstWhere('key', 'followers');

        expect($overview['net_followers']['value'])->toBe(50)
            ->and($overview['followers']['previous'])->toBe(1200)
            ->and($followers['current'])->toBe([null, null, 1230, null, null, null, null]);
    });

    it('omite seguidores líquidos quando não há API nem snapshot e explica o motivo', function () {
        fakeInstagram(followsBreakdown: false);

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk();

        expect(collect($response->json('data.overview'))->pluck('key'))->not->toContain('net_followers')
            ->and(collect($response->json('data.notes'))->implode(' '))->toContain('100+ seguidores');
    });

    it('retorna 409 quando falta o escopo de insights', function () {
        Http::fake(['*' => Http::response(['error' => ['code' => 10, 'type' => 'OAuthException', 'message' => 'Application does not have permission']], 400)]);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertStatus(409)
            ->assertJsonPath('error', 'analytics_reauth_required');
    });

    it('recusa períodos acima de 30 dias e presets que a API não suporta', function () {
        Http::fake();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=custom&start_date=2026-09-01&end_date=2026-10-10")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=lifetime")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_range']);

        Http::assertNothingSent();
    });

});

describe('OAuth do Instagram', function () {

    it('pede o escopo de insights na autorização', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $url = $this->withToken($user->test_token)
            ->getJson('/api/social-accounts/instagram/auth')
            ->assertOk()
            ->json('url');

        expect(urldecode($url))->toContain('instagram_business_manage_insights');
    });

});
