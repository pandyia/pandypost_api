<?php

use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const INSTAGRAM_SHORT_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
const INSTAGRAM_LONG_TOKEN_URL = 'https://graph.instagram.com/access_token*';
const INSTAGRAM_REFRESH_URL = 'https://graph.instagram.com/refresh_access_token*';
const INSTAGRAM_PROFILE_URL = 'https://graph.instagram.com/v25.0/me*';

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    config([
        'app.frontend_url' => 'https://app.pandypost.test',
        'services.meta.app_id' => 'meta_app_id',
        'services.meta.app_secret' => 'meta_app_secret',
        'services.meta.redirect' => 'https://api.pandypost.test/api/social-accounts/instagram/callback',
    ]);
});

function fakeInstagramAuthorization(array $overrides = []): void
{
    Http::fake([
        INSTAGRAM_SHORT_TOKEN_URL => Http::response(['access_token' => 'IG.curto', 'user_id' => '17841400000']),
        INSTAGRAM_PROFILE_URL => Http::response(['id' => '17841400000', 'username' => 'perfil_teste']),
        INSTAGRAM_LONG_TOKEN_URL => Http::response(['access_token' => 'IG.longo', 'expires_in' => 5184000]),
        ...$overrides,
    ]);
}

function instagramCallbackUrl($user, ?string $code = 'codigo'): string
{
    $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);

    return '/api/social-accounts/instagram/callback?'.http_build_query(array_filter([
        'state' => encrypt($workspace->uuid),
        'code' => $code,
    ]));
}

describe('conexão com o Instagram', function () {

    it('retorna a URL do Instagram com os escopos e o state do workspace', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $url = $this->withToken($user->test_token)
            ->getJson('/api/social-accounts/instagram/auth')
            ->assertOk()
            ->json('url');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        expect($url)->toStartWith('https://www.instagram.com/oauth/authorize?')
            ->and($query['client_id'])->toBe('meta_app_id')
            ->and($query['redirect_uri'])->toBe('https://api.pandypost.test/api/social-accounts/instagram/callback')
            ->and($query['scope'])->toBe('instagram_business_basic,instagram_business_content_publish')
            ->and(decrypt($query['state']))->toBe(Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id)->uuid);
    });

    it('retorna 403 para quem não tem permissão de conectar contas', function () {
        $this->withToken(createUserWithoutPermissions()->test_token)
            ->getJson('/api/social-accounts/instagram/auth')
            ->assertForbidden();
    });

    it('conecta a conta com o token de longa duração no workspace do state', function () {
        fakeInstagramAuthorization();
        $user = createUserWithPermissions();

        $this->get(instagramCallbackUrl($user))
            ->assertRedirect('https://app.pandypost.test/social-accounts/callback?success=1');

        $account = SocialAccount::withoutGlobalScopes()->sole();
        expect($account->workspace_id)->toBe($user->currentAccess->workspace_id)
            ->and($account->platform)->toBe('instagram')
            ->and($account->platform_id)->toBe('17841400000')
            ->and($account->nickname)->toBe('perfil_teste')
            ->and($account->access_token)->toBe('IG.longo')
            ->and($account->refresh_token)->toBeNull()
            ->and($account->expires_at->greaterThan(now()->addDays(59)))->toBeTrue();

        Http::assertSent(fn (Request $request) => $request->url() === INSTAGRAM_SHORT_TOKEN_URL
            && $request['code'] === 'codigo'
            && $request['client_secret'] === 'meta_app_secret');
    });

    it('recusa a conexão quando a troca pelo token de longa duração falha', function () {
        fakeInstagramAuthorization([INSTAGRAM_LONG_TOKEN_URL => Http::response(['error' => ['message' => 'falhou']], 400)]);
        $user = createUserWithPermissions();

        $this->get(instagramCallbackUrl($user))
            ->assertRedirect('https://app.pandypost.test/social-accounts/callback?error='.urlencode('Falha ao obter token de acesso.'));

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });

    it('recusa a conexão sem código ou sem token', function (?string $code, array $tokenResponse) {
        fakeInstagramAuthorization([INSTAGRAM_SHORT_TOKEN_URL => Http::response($tokenResponse, 400)]);
        $user = createUserWithPermissions();

        $this->get(instagramCallbackUrl($user, $code))->assertRedirectContains('?error=');

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'sem código' => [null, []],
        'sem token' => ['codigo', ['error_message' => 'invalid code']],
    ]);

    it('conecta a conta só no workspace do state', function () {
        fakeInstagramAuthorization();
        $owner = createUserWithPermissions();
        $other = createUserWithPermissions();

        $this->get(instagramCallbackUrl($owner));

        expect(SocialAccount::withoutGlobalScopes()->where('workspace_id', $other->currentAccess->workspace_id)->count())->toBe(0);
    });
});

describe('token do Instagram', function () {

    it('renova o token perto do vencimento', function () {
        Http::fake([INSTAGRAM_REFRESH_URL => Http::response(['access_token' => 'IG.renovado', 'expires_in' => 5184000])]);
        $account = createSocialAccount('instagram', createUserWithPermissions(), ['expires_at' => now()->addMinutes(3)]);

        expect($account->getValidToken())->toBe('IG.renovado');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://graph.instagram.com/refresh_access_token')
            && $request['grant_type'] === 'ig_refresh_token'
            && $request['access_token'] === 'instagram_access_token');
    });

    it('falha quando o Instagram recusa a renovação', function () {
        Http::fake([INSTAGRAM_REFRESH_URL => Http::response(['error' => ['message' => 'expired']], 400)]);
        $account = createSocialAccount('instagram', createUserWithPermissions(), ['expires_at' => now()->subMinute()]);

        expect(fn () => $account->getValidToken())
            ->toThrow(SocialAccountException::class, 'Não foi possível renovar o acesso ao Instagram. Reconecte a conta.');
    });

    it('desconecta sem chamar o Instagram (não há revogação)', function () {
        Http::fake();
        $user = createUserWithPermissions(['social_accounts.disconnect']);
        $account = createSocialAccount('instagram', $user);

        $this->withToken($user->test_token)->deleteJson("/api/social-accounts/{$account->uuid}")->assertOk();

        Http::assertNothingSent();
        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });
});
