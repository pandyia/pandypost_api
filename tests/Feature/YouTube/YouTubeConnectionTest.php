<?php

use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    config([
        'app.frontend_url' => 'https://app.pandypost.test',
        'services.google.client_id' => 'google_client_id',
        'services.google.client_secret' => 'google_client_secret',
        'services.google.redirect' => 'https://api.pandypost.test/api/social-accounts/google/callback',
    ]);
});

function fakeGoogleSocialiteUser(string $id = 'google_123'): void
{
    $googleUser = (new SocialiteUser)
        ->setRaw([])
        ->map(['id' => $id, 'name' => 'Canal do Teste', 'avatar' => 'https://yt3.ggpht.com/avatar.jpg'])
        ->setToken('ya29.token')
        ->setRefreshToken('1//refresh')
        ->setExpiresIn(3599);

    $driver = Mockery::mock(GoogleProvider::class);
    $driver->shouldReceive('stateless')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
}

function googleCallbackUrl(Workspace $workspace): string
{
    return '/api/social-accounts/google/callback?'.http_build_query(['state' => encrypt($workspace->uuid), 'code' => 'codigo']);
}

describe('conexão com o YouTube', function () {

    it('retorna a URL do Google com os escopos do YouTube, acesso offline e o state do workspace', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $url = $this->withToken($user->test_token)
            ->getJson('/api/social-accounts/google/auth')
            ->assertOk()
            ->json('url');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        expect($url)->toStartWith('https://accounts.google.com/o/oauth2/auth?')
            ->and($query['client_id'])->toBe('google_client_id')
            ->and($query['scope'])->toContain('https://www.googleapis.com/auth/youtube.upload')
            ->and($query['scope'])->toContain('https://www.googleapis.com/auth/youtube.readonly')
            ->and($query['scope'])->toContain('https://www.googleapis.com/auth/yt-analytics.readonly')
            ->and($query['access_type'])->toBe('offline')
            ->and($query['prompt'])->toBe('consent')
            ->and(decrypt($query['state']))->toBe(Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id)->uuid);
    });

    it('retorna 403 para quem não tem permissão de conectar contas', function () {
        $this->withToken(createUserWithoutPermissions()->test_token)
            ->getJson('/api/social-accounts/google/auth')
            ->assertForbidden();
    });

    it('recusa plataforma não suportada com 400', function (string $platform) {
        $this->withToken(createUserWithPermissions(['social_accounts.connect'])->test_token)
            ->getJson("/api/social-accounts/{$platform}/auth")
            ->assertStatus(400)
            ->assertJsonPath('error', 'platform_not_supported');
    })->with(['facebook', 'youtube']);

    it('conecta o canal no workspace do state', function () {
        fakeGoogleSocialiteUser();
        $user = createUserWithPermissions();
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

        $this->get(googleCallbackUrl($workspace))
            ->assertRedirect('https://app.pandypost.test/social-accounts/callback?success=1');

        $account = SocialAccount::withoutGlobalScopes()->sole();
        expect($account->workspace_id)->toBe($workspace->id)
            ->and($account->platform)->toBe('youtube')
            ->and($account->platform_id)->toBe('google_123')
            ->and($account->nickname)->toBe('Canal do Teste')
            ->and($account->avatar)->toBe('https://yt3.ggpht.com/avatar.jpg')
            ->and($account->access_token)->toBe('ya29.token')
            ->and($account->refresh_token)->toBe('1//refresh');
    });

    it('recusa a conexão quando o Google não troca o código', function () {
        $driver = Mockery::mock(GoogleProvider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andThrow(new RuntimeException('invalid_grant'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
        $user = createUserWithPermissions();

        $this->get(googleCallbackUrl(Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id)))
            ->assertRedirect('https://app.pandypost.test/social-accounts/callback?error='
                .urlencode('Falha ao trocar o código pelo token de acesso.'));

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('token do YouTube', function () {

    it('renova o token vencido', function () {
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.novo', 'expires_in' => 3599])]);
        $account = createSocialAccount('youtube', createUserWithPermissions(), ['expires_at' => now()->subMinute()]);

        expect($account->getValidToken())->toBe('ya29.novo')
            ->and($account->fresh()->expires_at->isFuture())->toBeTrue();

        Http::assertSent(fn (Request $request) => $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'youtube_refresh_token');
    });

    it('falha quando não há refresh token', function () {
        $account = createSocialAccount('youtube', createUserWithPermissions(), [
            'refresh_token' => null,
            'expires_at' => now()->subMinute(),
        ]);

        expect(fn () => $account->getValidToken())
            ->toThrow(SocialAccountException::class, 'Não foi possível renovar o acesso ao YouTube. Reconecte a conta.');
    });

    it('falha quando o Google recusa a renovação', function () {
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $account = createSocialAccount('youtube', createUserWithPermissions(), ['expires_at' => now()->subMinute()]);

        expect(fn () => $account->getValidToken())->toThrow(SocialAccountException::class);
        expect($account->fresh()->access_token)->toBe('youtube_access_token');
    });

    it('revoga o token no Google ao desconectar', function () {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response([])]);
        $user = createUserWithPermissions(['social_accounts.disconnect']);
        $account = createSocialAccount('youtube', $user);

        $this->withToken($user->test_token)
            ->deleteJson("/api/social-accounts/{$account->uuid}")
            ->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/revoke'
            && $request['token'] === 'youtube_access_token');
    });
});
