<?php

use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Factories\OAuthProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

// Caracterização (spec 014): getRedirectUrl/syncAccount dos 3 providers, antes da AbstractOAuthProvider.

beforeEach(function () {
    config([
        'services.google.client_id' => 'google-id',
        'services.google.client_secret' => 'google-secret',
        'services.google.redirect' => 'https://api.test/api/social-accounts/google/callback',
        'services.meta.app_id' => 'meta-id',
        'services.meta.app_secret' => 'meta-secret',
        'services.meta.redirect' => 'https://api.test/api/social-accounts/instagram/callback',
        'services.tiktok.client_key' => 'tt-key',
        'services.tiktok.client_secret' => 'tt-secret',
        'services.tiktok.redirect' => 'https://api.test/api/social-accounts/tiktok/callback',
    ]);
});

function oauthProvider(string $factoryKey)
{
    return app(OAuthProviderFactory::class)->make($factoryKey);
}

function stateFromUrl(string $url): string
{
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    return $query['state'];
}

function userWithoutWorkspace(): User
{
    return User::factory()->create();
}

function fakeGoogleUser(string $id = 'canal-1', ?string $refresh = 'refresh-g'): void
{
    $socialiteUser = (new SocialiteUser)->map(['id' => $id, 'name' => 'Canal Google', 'avatar' => 'https://g.test/a.jpg']);
    $socialiteUser->setToken('access-g')->setRefreshToken($refresh)->setExpiresIn(3600);

    Socialite::shouldReceive('driver->stateless->user')->andReturn($socialiteUser);
}

function fakeInstagramApi(array $overrides = []): void
{
    Http::fake(array_merge([
        'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-ig', 'user_id' => 'ig-1']),
        'graph.instagram.com/v25.0/me*' => Http::response(['id' => 'ig-1', 'username' => 'perfil_ig']),
        'graph.instagram.com/access_token*' => Http::response(['access_token' => 'long-ig', 'expires_in' => 5184000]),
    ], $overrides));
}

function fakeTikTokApi(array $overrides = []): void
{
    Http::fake(array_merge([
        'open.tiktokapis.com/v2/oauth/token/*' => Http::response([
            'access_token' => 'tt-access', 'refresh_token' => 'tt-refresh', 'open_id' => 'open-1',
            'expires_in' => 86400, 'scope' => 'user.info.basic,video.publish',
        ]),
        'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'Canal TT', 'avatar_url' => 'https://tt.test/a.jpg', 'username' => 'canal_tt']], 'error' => ['code' => 'ok']]),
    ], $overrides));
}

describe('getRedirectUrl', function () {

    it('sem workspace corrente lança oauthInitializationFailed nas 3 redes', function (string $factoryKey) {
        expect(fn () => oauthProvider($factoryKey)->getRedirectUrl(userWithoutWorkspace()))
            ->toThrow(SocialAccountException::class, 'Falha ao iniciar o fluxo OAuth.');
        expect(fn () => oauthProvider($factoryKey)->getRedirectUrl(null))
            ->toThrow(SocialAccountException::class);
    })->with(['google', 'instagram', 'tiktok']);

    it('o state cifrado leva o uuid do workspace e o usuário iniciador (spec 017) e a URL aponta para a rede', function (string $factoryKey, string $host) {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $url = oauthProvider($factoryKey)->getRedirectUrl($user);
        [$payload] = oauthStateOpen(stateFromUrl($url));

        expect($url)->toContain($host)
            ->and($payload['workspace_uuid'])->toBe($user->currentAccess->workspace->uuid)
            ->and($payload['user_id'])->toBe($user->id);
    })->with([
        'google' => ['google', 'accounts.google.com'],
        'instagram' => ['instagram', 'www.instagram.com/oauth/authorize'],
        'tiktok' => ['tiktok', 'www.tiktok.com/v2/auth/authorize'],
    ]);

    it('TikTok sem client_key lança oauthInitializationFailed', function () {
        config(['services.tiktok.client_key' => null]);
        $user = createUserWithPermissions([]);

        expect(fn () => oauthProvider('tiktok')->getRedirectUrl($user))
            ->toThrow(SocialAccountException::class, 'Falha ao iniciar o fluxo OAuth.');
    });

    it('Google pede acesso offline e os escopos de upload, leitura e analytics do YouTube', function () {
        $url = oauthProvider('google')->getRedirectUrl(createUserWithPermissions([]));

        expect(urldecode($url))->toContain('access_type=offline')->toContain('prompt=consent')
            ->toContain('youtube.upload')->toContain('youtube.readonly')->toContain('yt-analytics.readonly');
    });

    it('TikTok pede os escopos de publicação', function () {
        $url = oauthProvider('tiktok')->getRedirectUrl(createUserWithPermissions([]));

        expect(urldecode($url))->toContain('video.upload')->toContain('video.publish');
    });

});

describe('syncAccount: dono da conta', function () {

    it('com User grava o workspace corrente e o próprio usuário', function (string $factoryKey, string $platform) {
        $user = createUserWithPermissions([]);
        match ($factoryKey) {
            'google' => fakeGoogleUser(),
            'instagram' => fakeInstagramApi(),
            'tiktok' => fakeTikTokApi(),
        };

        $account = oauthProvider($factoryKey)->syncAccount($user, 'code-1');

        expect($account->workspace_id)->toBe($user->currentAccess->workspace_id)
            ->and($account->user_id)->toBe($user->id)
            ->and($account->platform)->toBe($platform);
    })->with([
        'google' => ['google', 'youtube'],
        'instagram' => ['instagram', 'instagram'],
        'tiktok' => ['tiktok', 'tiktok'],
    ]);

    it('o dono gravado é o usuário iniciador (contexto do callback), não o primeiro membro do workspace (spec 017)', function (string $factoryKey, string $platform) {
        $primeiro = createUserWithPermissions([]);
        $workspace = $primeiro->currentAccess->workspace;
        $iniciador = createUserWithPermissions([], true, $workspace);
        match ($factoryKey) {
            'google' => fakeGoogleUser(),
            'instagram' => fakeInstagramApi(),
            'tiktok' => fakeTikTokApi(),
        };

        $account = oauthProvider($factoryKey)->syncAccount($iniciador, 'code-1');

        expect($account->workspace_id)->toBe($workspace->id)
            ->and($account->user_id)->toBe($iniciador->id)
            ->and($account->user_id)->not->toBe($primeiro->id)
            ->and($account->platform)->toBe($platform);
    })->with([
        'google' => ['google', 'youtube'],
        'instagram' => ['instagram', 'instagram'],
        'tiktok' => ['tiktok', 'tiktok'],
    ]);

    it('reconectar a mesma conta atualiza o registro em vez de duplicar', function () {
        $user = createUserWithPermissions([]);
        fakeInstagramApi();

        oauthProvider('instagram')->syncAccount($user, 'code-1');
        oauthProvider('instagram')->syncAccount($user, 'code-2');

        expect(SocialAccount::withoutGlobalScopes()->where('platform', 'instagram')->count())->toBe(1);
    });

});

describe('syncAccount: Google', function () {

    it('grava tokens, apelido e avatar', function () {
        $user = createUserWithPermissions([]);
        fakeGoogleUser('canal-9');

        $account = oauthProvider('google')->syncAccount($user)->fresh();

        expect($account->platform_id)->toBe('canal-9')
            ->and($account->access_token)->toBe('access-g')
            ->and($account->refresh_token)->toBe('refresh-g')
            ->and($account->nickname)->toBe('Canal Google')
            ->and($account->avatar)->toBe('https://g.test/a.jpg')
            ->and($account->expires_at->isFuture())->toBeTrue();
    });

    it('falha do Socialite vira oauthTokenExchangeFailed', function () {
        Socialite::shouldReceive('driver->stateless->user')->andThrow(new RuntimeException('invalid_grant'));

        expect(fn () => oauthProvider('google')->syncAccount(createUserWithPermissions([])))
            ->toThrow(SocialAccountException::class);
    });

});

describe('syncAccount: Instagram', function () {

    it('sem code lança authFailed e não chama a rede', function () {
        Http::fake();

        expect(fn () => oauthProvider('instagram')->syncAccount(createUserWithPermissions([])))
            ->toThrow(SocialAccountException::class, 'Instagram');
        Http::assertNothingSent();
    });

    it('troca de código sem access_token/user_id lança authFailed', function () {
        fakeInstagramApi(['api.instagram.com/oauth/access_token' => Http::response(['error_message' => 'x'], 400)]);

        expect(fn () => oauthProvider('instagram')->syncAccount(createUserWithPermissions([]), 'ruim'))
            ->toThrow(SocialAccountException::class, 'Instagram');
    });

    it('grava o token de longa duração, o username e refresh_token nulo', function () {
        fakeInstagramApi();

        $account = oauthProvider('instagram')->syncAccount(createUserWithPermissions([]), 'code-1')->fresh();

        expect($account->platform_id)->toBe('ig-1')
            ->and($account->access_token)->toBe('long-ig')
            ->and($account->refresh_token)->toBeNull()
            ->and($account->nickname)->toBe('perfil_ig')
            ->and($account->expires_at->greaterThan(now()->addDays(59)))->toBeTrue();
    });

    it('sem username no perfil usa "Instagram User"', function () {
        fakeInstagramApi(['graph.instagram.com/v25.0/me*' => Http::response(['id' => 'ig-1'])]);

        $account = oauthProvider('instagram')->syncAccount(createUserWithPermissions([]), 'code-1');

        expect($account->nickname)->toBe('Instagram User');
    });

    it('se a troca por token de longa duração falhar, fica o token curto por 1 hora', function () {
        fakeInstagramApi(['graph.instagram.com/access_token*' => Http::response(['error' => 'x'], 400)]);

        $account = oauthProvider('instagram')->syncAccount(createUserWithPermissions([]), 'code-1')->fresh();

        expect($account->access_token)->toBe('short-ig')
            ->and($account->expires_at->lessThanOrEqualTo(now()->addHour()->addMinute()))->toBeTrue()
            ->and($account->expires_at->isFuture())->toBeTrue();
    });

});

describe('syncAccount: TikTok', function () {

    it('sem code lança authFailed', function () {
        Http::fake();

        expect(fn () => oauthProvider('tiktok')->syncAccount(createUserWithPermissions([])))
            ->toThrow(SocialAccountException::class, 'TikTok');
        Http::assertNothingSent();
    });

    it('resposta sem access_token/open_id lança oauthTokenExchangeFailed', function () {
        fakeTikTokApi(['open.tiktokapis.com/v2/oauth/token/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        expect(fn () => oauthProvider('tiktok')->syncAccount(createUserWithPermissions([]), 'ruim'))
            ->toThrow(SocialAccountException::class);
    });

    it('grava tokens, perfil e os escopos realmente concedidos', function () {
        fakeTikTokApi();

        $account = oauthProvider('tiktok')->syncAccount(createUserWithPermissions([]), 'code-1')->fresh();

        expect($account->platform_id)->toBe('open-1')
            ->and($account->access_token)->toBe('tt-access')
            ->and($account->refresh_token)->toBe('tt-refresh')
            ->and($account->nickname)->toBe('Canal TT')
            ->and($account->username)->toBe('canal_tt')
            ->and($account->avatar)->toBe('https://tt.test/a.jpg')
            ->and($account->scopes)->toBe('user.info.basic,video.publish');
    });

    it('perfil indisponível não impede a conexão: nickname padrão "TikTok"', function () {
        fakeTikTokApi(['open.tiktokapis.com/v2/user/info/*' => Http::response([], 500)]);

        $account = oauthProvider('tiktok')->syncAccount(createUserWithPermissions([]), 'code-1');

        expect($account->nickname)->toBe('TikTok')->and($account->username)->toBeNull();
    });

});

describe('plataforma desconhecida', function () {

    it('GET /social-accounts/{platform}/auth responde 400 platform_not_supported (antes: 500)', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $this->withToken($user->test_token)
            ->getJson('/api/social-accounts/orkut/auth')
            ->assertStatus(400)
            ->assertJsonPath('error', 'platform_not_supported')
            ->assertJsonPath('message', "Plataforma 'orkut' não é suportada.");
    });

    it('o callback redireciona ao front com o erro, sem criar conta', function () {
        $user = createUserWithPermissions([]);
        $state = oauthStateFor($user, 'google');

        $this->get('/api/social-accounts/orkut/callback?code=abc&state='.urlencode($state))
            ->assertRedirectContains('error='.urlencode("Plataforma 'orkut' não é suportada."));

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });

});
