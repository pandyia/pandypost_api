<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function tiktokCreatorInfoUrl($account): string
{
    return "/api/social-accounts/{$account->uuid}/tiktok/creator-info";
}

describe('GET /api/social-accounts/{socialAccount}/tiktok/creator-info (RF-3)', function () {

    it('200: devolve as opções do criador vindas do creator_info/query', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user);
        tiktokFakeApi(['creator' => [
            'creator_nickname' => 'Canal do Gabriel',
            'privacy_level_options' => ['SELF_ONLY'],
            'comment_disabled' => true,
            'duet_disabled' => false,
            'stitch_disabled' => true,
            'max_video_post_duration_sec' => 180,
        ]]);

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))
            ->assertOk()
            ->assertJsonPath('data.creator_nickname', 'Canal do Gabriel')
            ->assertJsonPath('data.privacy_level_options', ['SELF_ONLY'])
            ->assertJsonPath('data.comment_disabled', true)
            ->assertJsonPath('data.duet_disabled', false)
            ->assertJsonPath('data.stitch_disabled', true)
            ->assertJsonPath('data.max_video_post_duration_sec', 180);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/post/publish/creator_info/query/')
            && $r->hasHeader('Authorization', 'Bearer token-valido'));
    });

    it('as privacidades oferecidas são exatamente as que o TikTok devolve para a conta (sem acrescentar)', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user);
        tiktokFakeApi(['creator' => ['privacy_level_options' => ['SELF_ONLY', 'MUTUAL_FOLLOW_FRIENDS']]]);

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))
            ->assertOk()
            ->assertJsonPath('data.privacy_level_options', ['SELF_ONLY', 'MUTUAL_FOLLOW_FRIENDS']);
    });

    it('não expõe token, refresh token nem o body cru do TikTok', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user);
        tiktokFakeApi();

        $response = $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))->assertOk();

        expect($response->getContent())
            ->not->toContain('token-valido')->not->toContain('refresh-valido')
            ->not->toContain('access_token')->not->toContain('log_id');
    });

    it('401 sem autenticação', function () {
        $owner = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($owner);
        tiktokFakeApi();

        $this->withToken($owner->test_token)->getJson(tiktokCreatorInfoUrl($account))->assertOk(); // controle: a rota existe

        // withToken() fica nos headers padrão e o guard do Sanctum guarda o usuário; sem limpar, a 2ª chamada ainda iria autenticada.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson(tiktokCreatorInfoUrl($account))->assertUnauthorized();
        Http::assertSentCount(1); // só a chamada do controle
    });

    it('403 sem social_accounts.view e o TikTok não é chamado', function () {
        $user = createUserWithPermissions(['posts.view']);
        $account = tiktokAccount($user);
        tiktokFakeApi();

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))->assertForbidden();
        Http::assertNothingSent();
    });

    it('404 para conta de outro workspace e o TikTok não é chamado (isolamento)', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $propria = tiktokAccount($user);
        $outra = tiktokAccount(createUserWithPermissions([]));
        tiktokFakeApi();

        // Controle: a rota existe e responde 200 para a conta do próprio workspace.
        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($propria))->assertOk();
        Http::assertSentCount(1);

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($outra))->assertNotFound();
        Http::assertSentCount(1); // nenhuma chamada nova ao TikTok com o token da conta alheia
    });

    it('404 para uuid inexistente', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $propria = tiktokAccount($user);
        tiktokFakeApi();

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($propria))->assertOk(); // controle: a rota existe
        $this->withToken($user->test_token)->getJson('/api/social-accounts/'.fake()->uuid().'/tiktok/creator-info')->assertNotFound();
    });

    it('409 quando a conta não tem o escopo de publicação, sem chamar o TikTok', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user, ['scopes' => 'user.info.basic,video.list']);
        tiktokFakeApi();

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))
            ->assertStatus(409)
            ->assertJsonStructure(['error', 'message']);

        Http::assertNothingSent();
    });

    it('409 para conta antiga com scopes nulo (precisa reconectar)', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user, ['scopes' => null]);
        tiktokFakeApi();

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))->assertStatus(409);
    });

    it('409 quando o TikTok recusa o token (access_token_invalid), com mensagem em pt-BR sem o body cru', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user);
        tiktokFakeApi(['creator_response' => Http::response([
            'data' => [],
            'error' => ['code' => 'access_token_invalid', 'message' => 'The access token is invalid or not authorized', 'log_id' => 'LOG-SECRETO-1'],
        ], 401)]);

        $response = $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))->assertStatus(409);

        expect($response->getContent())
            ->not->toContain('LOG-SECRETO-1')
            ->not->toContain('The access token is invalid')
            ->not->toContain('token-valido');
    });

    it('token expirado é renovado antes de consultar o TikTok', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user, ['expires_at' => now()->subHour()]);
        tiktokFakeApi();

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'creator_info/query') && $r->hasHeader('Authorization', 'Bearer novo-access-token'));
    });

    it('404: conta de outra plataforma no mesmo workspace não consulta o TikTok', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $instagram = connectedSocialAccount($user, 'instagram');
        Http::fake();

        $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($instagram))->assertNotFound();

        Http::assertNothingSent();
    });

    it('VULN-TT-04: falha de conexão com o TikTok vira 502 tratado, sem 500 nem URL na resposta', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user);
        config(['app.debug' => false]);
        Http::fake(['open.tiktokapis.com/*' => fn () => throw new ConnectionException('cURL error 28 for https://open.tiktokapis.com/v2/post/publish/creator_info/query/')]);

        $response = $this->withToken($user->test_token)->getJson(tiktokCreatorInfoUrl($account))
            ->assertStatus(502)
            ->assertJsonPath('error', 'tiktok_api_unavailable');

        expect($response->getContent())->not->toContain('open.tiktokapis.com')->not->toContain('cURL');
    });

});
