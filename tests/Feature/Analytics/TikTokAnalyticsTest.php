<?php

use App\Models\SocialAccount;
use App\Models\SocialAccountMetricSnapshot;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Carbon::setTestNow('2026-10-14 12:00:00');
    config(['services.tiktok' => ['client_key' => 'ck', 'client_secret' => 'cs', 'redirect' => 'https://api.test/api/social-accounts/tiktok/callback']]);
    $this->user = createUserWithPermissions([]);
    $this->account = connectedSocialAccount($this->user, 'tiktok');
});

afterEach(fn () => Carbon::setTestNow());

function tiktokVideo(string $id, string $publishedAt, int $views, int $likes, int $comments = 0, int $shares = 0, int $duration = 30): array
{
    return [
        'id' => $id,
        'title' => "Vídeo {$id}",
        'create_time' => Carbon::parse($publishedAt, 'UTC')->getTimestamp(),
        'cover_image_url' => "https://cdn/{$id}.jpg",
        'share_url' => "https://tiktok.com/@conta/video/{$id}",
        'duration' => $duration,
        'view_count' => $views,
        'like_count' => $likes,
        'comment_count' => $comments,
        'share_count' => $shares,
    ];
}

function fakeTikTok(): void
{
    Http::fake([
        'open.tiktokapis.com/v2/user/info/*' => Http::response([
            'data' => ['user' => ['open_id' => 'oid', 'display_name' => 'Conta', 'follower_count' => 5300, 'following_count' => 10, 'likes_count' => 90000, 'video_count' => 120]],
            'error' => ['code' => 'ok'],
        ]),
        'open.tiktokapis.com/v2/video/list/*' => Http::response([
            'data' => [
                'videos' => [
                    tiktokVideo('v1', '2026-10-12 22:00:00', 20000, 1500, 100, 400, 12),
                    tiktokVideo('v2', '2026-10-09 15:00:00', 4000, 200, 20, 20, 45),
                    tiktokVideo('v3', '2026-10-03 15:00:00', 8000, 400, 40, 60, 45),
                    tiktokVideo('v4', '2026-09-20 15:00:00', 99999, 1, 1, 1, 45),
                ],
                'cursor' => 1,
                'has_more' => false,
            ],
            'error' => ['code' => 'ok'],
        ]),
    ]);
}

describe('dashboard do TikTok', function () {

    it('soma os vídeos publicados no período e compara com o período anterior', function () {
        fakeTikTok();

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk()
            ->assertJsonPath('data.platform', 'tiktok')
            ->assertJsonPath('data.capabilities.has_daily_series', false);

        $overview = collect($response->json('data.overview'))->keyBy('key');

        expect($overview['views'])->toMatchArray(['value' => 24000, 'previous' => 8000, 'trend' => 200])
            ->and($overview['posts'])->toMatchArray(['value' => 2, 'previous' => 1])
            ->and($overview['engagement_rate']['value'])->toBe(9.33)
            ->and($overview['followers']['value'])->toBe(5300)
            ->and($overview['total_likes']['value'])->toBe(90000)
            ->and($overview->keys())->not->toContain('net_followers', 'likes_gained');

        expect(collect($response->json('data.top_content.items'))->pluck('id')->all())->toBe(['v1', 'v2'])
            ->and($response->json('data.top_content.items.0.permalink'))->toBe('https://tiktok.com/@conta/video/v1')
            ->and(collect($response->json('data.notes'))->implode(' '))->toContain('acumulados desde a publicação');
    });

    it('calcula seguidores e curtidas ganhos com snapshot nas duas pontas', function () {
        fakeTikTok();

        SocialAccountMetricSnapshot::create(['social_account_id' => $this->account->id, 'date' => '2026-10-07', 'followers' => 5000, 'total_likes' => 85000]);

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk();

        $overview = collect($response->json('data.overview'))->keyBy('key');

        expect($overview['net_followers']['value'])->toBe(300)
            ->and($overview['likes_gained']['value'])->toBe(5000)
            ->and($overview['followers']['previous'])->toBe(5000);
    });

    it('distribui visualizações por dia de publicação e por duração', function () {
        fakeTikTok();

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk();

        $views = collect($response->json('data.time_series.series'))->firstWhere('key', 'views');

        // v1 foi publicado 22:00 UTC de 12/10 = 19:00 em São Paulo, mesmo dia.
        expect($views['current'])->toBe([0, 4000, 0, 0, 20000, 0, 0])
            ->and($response->json('data.breakdowns.0.items'))->toBe([
                ['key' => 'up_to_15s', 'label' => 'Até 15s', 'value' => 20000],
                ['key' => 'up_to_60s', 'label' => '16s a 1min', 'value' => 4000],
            ]);
    });

    it('retorna 409 para token inválido (ex.: contas criadas pelo antigo stub)', function () {
        Http::fake(['*' => Http::response(['error' => ['code' => 'access_token_invalid', 'message' => 'invalid']], 401)]);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertStatus(409)
            ->assertJsonPath('error', 'analytics_reauth_required');
    });

    it('renova o token expirado antes de consultar a API', function () {
        $this->account->update(['expires_at' => now()->subHour()]);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'novo-token', 'refresh_token' => 'novo-refresh', 'expires_in' => 86400]),
        ]);
        fakeTikTok();

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$this->account->uuid}/dashboard?date_range=last_7_days")
            ->assertOk();

        expect($this->account->fresh())
            ->access_token->toBe('novo-token')
            ->refresh_token->toBe('novo-refresh');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'video/list') && $request->hasHeader('Authorization', 'Bearer novo-token'));
    });

});

describe('OAuth do TikTok', function () {

    it('gera a URL de autorização real com os escopos de leitura', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $url = $this->withToken($user->test_token)
            ->getJson('/api/social-accounts/tiktok/auth')
            ->assertOk()
            ->json('url');

        expect($url)->toStartWith('https://www.tiktok.com/v2/auth/authorize/')
            ->and(urldecode($url))->toContain('user.info.stats', 'video.list', 'client_key=ck')
            ->and($url)->not->toContain('mock_tiktok_code');
    });

    it('falha sem credenciais configuradas, sem criar conta falsa', function () {
        config(['services.tiktok.client_key' => null]);
        $user = createUserWithPermissions(['social_accounts.connect']);

        $this->withToken($user->test_token)
            ->getJson('/api/social-accounts/tiktok/auth')
            ->assertStatus(500)
            ->assertJsonPath('error', 'oauth_initialization_failed');

        expect(SocialAccount::withoutGlobalScopes()->where('platform', 'tiktok')->count())->toBe(1);
    });

    it('troca o code pelos tokens e cria a conta com o open_id', function () {
        $workspace = Workspace::withoutGlobalScopes()->find($this->user->currentAccess->workspace_id);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'open_id' => 'open-123', 'expires_in' => 86400, 'refresh_expires_in' => 31536000]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'open-123', 'display_name' => 'Criador', 'avatar_url' => 'https://cdn/a.jpg']], 'error' => ['code' => 'ok']]),
        ]);

        $this->get('/api/social-accounts/tiktok/callback?code=abc&state='.urlencode(encrypt($workspace->uuid)))
            ->assertRedirectContains('success=1');

        $account = SocialAccount::withoutGlobalScopes()->where('platform_id', 'open-123')->first();

        expect($account)->not->toBeNull()
            ->and($account->nickname)->toBe('Criador')
            ->and($account->access_token)->toBe('at')
            ->and($account->refresh_token)->toBe('rt')
            ->and($account->workspace_id)->toBe($workspace->id);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'oauth/token') && $request['grant_type'] === 'authorization_code' && $request['code'] === 'abc');
    });

    it('redireciona com erro quando a troca do code falha', function () {
        $workspace = Workspace::withoutGlobalScopes()->find($this->user->currentAccess->workspace_id);
        Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->get('/api/social-accounts/tiktok/callback?code=abc&state='.urlencode(encrypt($workspace->uuid)))
            ->assertRedirectContains('error=');

        expect(SocialAccount::withoutGlobalScopes()->where('platform', 'tiktok')->count())->toBe(1);
    });

    it('revoga o token no TikTok ao desconectar', function () {
        $user = createUserWithPermissions(['social_accounts.disconnect']);
        $account = connectedSocialAccount($user, 'tiktok');
        Http::fake();

        $this->withToken($user->test_token)
            ->deleteJson("/api/social-accounts/{$account->uuid}")
            ->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'oauth/revoke') && $request['token'] === 'token-valido');
    });

});
