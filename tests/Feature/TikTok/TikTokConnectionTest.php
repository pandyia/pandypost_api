<?php

use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\OAuthProviders\TikTokOAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const TIKTOK_USER_INFO_URL = 'https://open.tiktokapis.com/v2/user/info/*';

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    config([
        'app.frontend_url' => 'https://app.pandypost.test',
        'services.tiktok.client_key' => 'test_client_key',
        'services.tiktok.client_secret' => 'test_client_secret',
        'services.tiktok.redirect' => 'https://api.pandypost.test/api/social-accounts/tiktok/callback',
    ]);
});

function fakeTikTokAuthorization(array $tokenOverrides = []): void
{
    Http::fake([
        TikTokOAuthProvider::TOKEN_URL => Http::response(array_merge([
            'access_token' => 'act.novo_token',
            'refresh_token' => 'rft.novo_refresh',
            'open_id' => 'open_id_123',
            'scope' => 'user.info.basic,video.publish',
            'expires_in' => 86400,
        ], $tokenOverrides)),
        TIKTOK_USER_INFO_URL => Http::response([
            'data' => ['user' => ['display_name' => 'Criador TikTok', 'avatar_url' => 'https://p16.tiktokcdn.com/avatar.jpg']],
        ]),
    ]);
}

function tiktokCallbackUrl(Workspace $workspace, ?string $code = 'codigo_valido'): string
{
    return '/api/social-accounts/tiktok/callback?'.http_build_query(array_filter([
        'state' => encrypt($workspace->uuid),
        'code' => $code,
    ]));
}

function workspaceOf($user): Workspace
{
    return Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);
}

describe('URL de autorização', function () {

    it('retorna a URL do TikTok com os escopos de publicação e o state do workspace', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $url = $this->withToken($user->test_token)
            ->getJson('/api/social-accounts/tiktok/auth')
            ->assertOk()
            ->json('url');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        expect($url)->toStartWith('https://www.tiktok.com/v2/auth/authorize/?')
            ->and($query['client_key'])->toBe('test_client_key')
            ->and($query['scope'])->toBe('user.info.basic,video.publish')
            ->and($query['redirect_uri'])->toBe('https://api.pandypost.test/api/social-accounts/tiktok/callback')
            ->and(decrypt($query['state']))->toBe(workspaceOf($user)->uuid);
    });

    it('retorna 403 para quem não tem permissão de conectar contas', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)
            ->getJson('/api/social-accounts/tiktok/auth')
            ->assertForbidden();
    });
});

describe('callback do OAuth', function () {

    it('conecta a conta no workspace do state e redireciona com sucesso', function () {
        fakeTikTokAuthorization();
        $user = createUserWithPermissions();
        $workspace = workspaceOf($user);

        $this->get(tiktokCallbackUrl($workspace))
            ->assertRedirect('https://app.pandypost.test/social-accounts/callback?success=1');

        $account = SocialAccount::withoutGlobalScopes()->sole();

        expect($account->workspace_id)->toBe($workspace->id)
            ->and($account->user_id)->toBe($user->id)
            ->and($account->platform)->toBe('tiktok')
            ->and($account->platform_id)->toBe('open_id_123')
            ->and($account->nickname)->toBe('Criador TikTok')
            ->and($account->avatar)->toBe('https://p16.tiktokcdn.com/avatar.jpg')
            ->and($account->access_token)->toBe('act.novo_token')
            ->and($account->refresh_token)->toBe('rft.novo_refresh');

        Http::assertSent(fn (Request $request) => $request->url() === TikTokOAuthProvider::TOKEN_URL
            && $request['code'] === 'codigo_valido'
            && $request['grant_type'] === 'authorization_code');
    });

    it('atualiza a conta existente ao reconectar o mesmo usuário do TikTok', function () {
        $user = createUserWithPermissions();
        $workspace = workspaceOf($user);

        $token = ['open_id' => 'open_id_123', 'scope' => 'user.info.basic,video.publish'];

        Http::fake([
            TikTokOAuthProvider::TOKEN_URL => Http::sequence()
                ->push([...$token, 'access_token' => 'act.primeiro'])
                ->push([...$token, 'access_token' => 'act.segundo']),
            TIKTOK_USER_INFO_URL => Http::response(['data' => ['user' => ['display_name' => 'Criador TikTok']]]),
        ]);

        $this->get(tiktokCallbackUrl($workspace));
        $this->get(tiktokCallbackUrl($workspace));

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(1)
            ->and(SocialAccount::withoutGlobalScopes()->sole()->access_token)->toBe('act.segundo');
    });

    it('usa nome padrão quando o perfil do TikTok não responde', function () {
        Http::fake([
            TikTokOAuthProvider::TOKEN_URL => Http::response([
                'access_token' => 'act.token', 'open_id' => 'open_id_123', 'scope' => 'user.info.basic,video.publish',
            ]),
            TIKTOK_USER_INFO_URL => Http::response([], 500),
        ]);
        $user = createUserWithPermissions();

        $this->get(tiktokCallbackUrl(workspaceOf($user)))->assertRedirectContains('success=1');

        expect(SocialAccount::withoutGlobalScopes()->sole()->nickname)->toBe('TikTok User');
    });

    it('recusa a conexão quando o usuário não autoriza a publicação de vídeos', function () {
        fakeTikTokAuthorization(['scope' => 'user.info.basic']);
        $user = createUserWithPermissions();

        $this->get(tiktokCallbackUrl(workspaceOf($user)))
            ->assertRedirect('https://app.pandypost.test/social-accounts/callback?error='
                .urlencode('Autorize todas as permissões solicitadas pelo TikTok para conectar a conta.'));

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });

    it('recusa a conexão sem código de autorização', function () {
        fakeTikTokAuthorization();
        $user = createUserWithPermissions();

        $this->get(tiktokCallbackUrl(workspaceOf($user), code: null))
            ->assertRedirectContains('?error=');

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('recusa a conexão quando o TikTok não devolve token', function () {
        Http::fake([TikTokOAuthProvider::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);
        $user = createUserWithPermissions();

        $this->get(tiktokCallbackUrl(workspaceOf($user)))
            ->assertRedirectContains('?error=');

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });

    it('recusa state inválido', function () {
        fakeTikTokAuthorization();

        $this->get('/api/social-accounts/tiktok/callback?state=adulterado&code=codigo_valido')
            ->assertRedirectContains('?error=');

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });

    it('conecta a conta só no workspace do state, nunca em outro', function () {
        fakeTikTokAuthorization();
        $userA = createUserWithPermissions();
        $userB = createUserWithPermissions(['social_accounts.view']);

        $this->get(tiktokCallbackUrl(workspaceOf($userA)));

        expect(SocialAccount::withoutGlobalScopes()->where('workspace_id', workspaceOf($userB)->id)->count())->toBe(0);

        $this->withToken($userB->test_token)
            ->getJson('/api/social-accounts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('renovação de token', function () {

    it('renova o token vencido e guarda o novo refresh token', function () {
        $user = createUserWithPermissions();
        $account = createTikTokAccount($user, ['expires_at' => now()->subMinute()]);

        Http::fake([TikTokOAuthProvider::TOKEN_URL => Http::response([
            'access_token' => 'act.renovado',
            'refresh_token' => 'rft.renovado',
            'expires_in' => 86400,
        ])]);

        expect($account->getValidToken())->toBe('act.renovado');

        $account->refresh();
        expect($account->refresh_token)->toBe('rft.renovado')
            ->and($account->expires_at->isFuture())->toBeTrue();

        Http::assertSent(fn (Request $request) => $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'tiktok_refresh_token');
    });

    it('não chama o TikTok enquanto o token ainda é válido', function () {
        Http::fake();
        $account = createTikTokAccount(createUserWithPermissions());

        expect($account->getValidToken())->toBe('tiktok_access_token');
        Http::assertNothingSent();
    });

    it('lança erro de domínio quando não há refresh token', function () {
        $account = createTikTokAccount(createUserWithPermissions(), [
            'refresh_token' => null,
            'expires_at' => now()->subMinute(),
        ]);

        expect(fn () => $account->getValidToken())
            ->toThrow(SocialAccountException::class, 'Não foi possível renovar o acesso ao TikTok. Reconecte a conta.');
    });

    it('lança erro de domínio quando o TikTok recusa a renovação', function () {
        Http::fake([TikTokOAuthProvider::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);
        $account = createTikTokAccount(createUserWithPermissions(), ['expires_at' => now()->subMinute()]);

        expect(fn () => $account->getValidToken())->toThrow(SocialAccountException::class);
        expect($account->fresh()->access_token)->toBe('tiktok_access_token');
    });
});

describe('desconexão', function () {

    it('revoga o token no TikTok e remove a conta', function () {
        Http::fake([TikTokOAuthProvider::REVOKE_URL => Http::response([])]);
        $user = createUserWithPermissions(['social_accounts.disconnect']);
        $account = createTikTokAccount($user);

        $this->withToken($user->test_token)
            ->deleteJson("/api/social-accounts/{$account->uuid}")
            ->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === TikTokOAuthProvider::REVOKE_URL
            && $request['token'] === 'tiktok_access_token'
            && $request['client_key'] === 'test_client_key');

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });
});
