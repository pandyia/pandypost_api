<?php

use App\Models\Access;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

// Spec 017 (RF-4, RF-5, RF-7): state do OAuth de uso único, com nonce, TTL de 10 min e vínculo ao iniciador.

beforeEach(function () {
    config(['app.frontend_url' => 'https://front.test']);
});

/** Faz a rede responder como se o code fosse válido. Google usa o Socialite; Instagram e TikTok, o Http. */
function stateFakeNetwork(string $key): void
{
    match ($key) {
        'google' => (function () {
            $u = (new SocialiteUser)->map(['id' => 'canal-1', 'name' => 'Canal', 'avatar' => 'https://g.test/a.jpg']);
            $u->setToken('access-g')->setRefreshToken('refresh-g')->setExpiresIn(3600);
            Socialite::shouldReceive('driver->stateless->user')->andReturn($u);
        })(),
        'instagram' => Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-ig', 'user_id' => 'ig-1']),
            'graph.instagram.com/v25.0/me*' => Http::response(['id' => 'ig-1', 'username' => 'perfil_ig']),
            'graph.instagram.com/access_token*' => Http::response(['access_token' => 'long-ig', 'expires_in' => 5184000]),
        ]),
        'tiktok' => Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'open_id' => 'oid-1', 'expires_in' => 86400, 'scope' => TIKTOK_SCOPES_OK]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'Canal TT']], 'error' => ['code' => 'ok']]),
        ]),
    };
}

function stateCallback(string $platform, string $state, string $code = 'code-1')
{
    return test()->get("/api/social-accounts/{$platform}/callback?code={$code}&state=".urlencode($state));
}

function stateAccounts()
{
    return SocialAccount::withoutGlobalScopes();
}

$redes = [
    'google' => ['google', 'youtube'],
    'instagram' => ['instagram', 'instagram'],
    'tiktok' => ['tiktok', 'tiktok'],
];

describe('state válido (primeira vez)', function () use ($redes) {

    it('cria a conta no workspace do state com user_id do iniciador e redireciona com success=1', function (string $key, string $platform) {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, $key);
        stateFakeNetwork($key);

        stateCallback($key, $state)->assertRedirectContains('https://front.test/social-accounts/callback?success=1');

        $account = stateAccounts()->where('platform', $platform)->sole();
        expect($account->workspace_id)->toBe($user->currentAccess->workspace_id)
            ->and($account->user_id)->toBe($user->id);
    })->with($redes);

    it('RF-5: grava o iniciador e não o primeiro membro do workspace', function (string $key, string $platform) {
        $primeiro = createUserWithPermissions(['social_accounts.connect']);
        $workspace = $primeiro->currentAccess->workspace;
        $iniciador = createUserWithPermissions(['social_accounts.connect'], true, $workspace);
        $state = oauthStateFor($iniciador, $key);
        stateFakeNetwork($key);

        stateCallback($key, $state)->assertRedirectContains('success=1');

        $account = stateAccounts()->where('platform', $platform)->sole();
        expect($account->user_id)->toBe($iniciador->id)->not->toBe($primeiro->id)
            ->and($account->workspace_id)->toBe($workspace->id);
    })->with($redes);

    it('o state emitido leva workspace_uuid, user_id do iniciador, nonce e expiração (cifrado, não é o uuid puro)', function (string $key) {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $state = oauthStateFor($user, $key);
        [$payload] = oauthStateOpen($state);

        expect($state)->not->toContain($user->currentAccess->workspace->uuid)
            ->and($payload)->toHaveKeys(['workspace_uuid', 'user_id', 'nonce'])
            ->and($payload['workspace_uuid'])->toBe($user->currentAccess->workspace->uuid)
            ->and($payload['user_id'])->toBe($user->id)
            ->and($payload['nonce'])->toBeString()->not->toBeEmpty();
    })->with(['google', 'instagram', 'tiktok']);

    it('dois states emitidos para o mesmo usuário têm nonces diferentes', function (string $key) {
        $user = createUserWithPermissions(['social_accounts.connect']);

        [$a] = oauthStateOpen(oauthStateFor($user, $key));
        [$b] = oauthStateOpen(oauthStateFor($user, $key));

        expect($a['nonce'])->not->toBe($b['nonce']);
    })->with(['google', 'instagram', 'tiktok']);

    it('ainda vale perto do fim do TTL (9 minutos)', function (string $key, string $platform) {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, $key);
        stateFakeNetwork($key);
        $this->travel(9)->minutes();

        stateCallback($key, $state)->assertRedirectContains('success=1');

        expect(stateAccounts()->where('platform', $platform)->count())->toBe(1);
    })->with($redes);

});

describe('state rejeitado: error= no redirect e nenhuma conta criada', function () use ($redes) {

    it('replay: o mesmo state usado duas vezes funciona só na primeira', function (string $key, string $platform) {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, $key);
        stateFakeNetwork($key);

        stateCallback($key, $state)->assertRedirectContains('success=1');
        $accountId = stateAccounts()->where('platform', $platform)->sole()->id;
        stateAccounts()->whereKey($accountId)->update(['nickname' => 'marca-do-teste']);

        $segunda = stateCallback($key, $state, 'code-2');

        $segunda->assertRedirectContains('error=');
        expect($segunda->headers->get('Location'))->not->toContain('success=1')
            ->and(stateAccounts()->where('platform', $platform)->count())->toBe(1)
            ->and(stateAccounts()->find($accountId)->nickname)->toBe('marca-do-teste');
    })->with($redes);

    it('expirado: após 10 minutos o state é recusado', function (string $key) {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, $key);
        stateFakeNetwork($key);
        $this->travel(11)->minutes();

        stateCallback($key, $state)->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
    })->with(['google', 'instagram', 'tiktok']);

    it('adulterado: state com caracteres alterados é recusado', function (string $key) {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, $key);
        stateFakeNetwork($key);
        $adulterado = substr($state, 0, -4).strrev(substr($state, -4));
        if ($adulterado === $state) {
            $adulterado = substr($state, 0, -1).($state[-1] === 'A' ? 'B' : 'A');
        }

        stateCallback($key, $adulterado)->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
    })->with(['google', 'instagram', 'tiktok']);

    it('adulterado: payload com user_id trocado e re-selado com a chave do app é recusado (nonce está preso ao iniciador)', function (string $key) {
        $workspace = createUserWithPermissions(['social_accounts.connect'])->currentAccess->workspace;
        $iniciador = createUserWithPermissions(['social_accounts.connect'], true, $workspace);
        $outro = createUserWithPermissions(['social_accounts.connect'], true, $workspace);
        [$payload, $mode] = oauthStateOpen(oauthStateFor($iniciador, $key));
        $forjado = oauthStateSeal(array_merge($payload, ['user_id' => $outro->id]), $mode);
        stateFakeNetwork($key);

        stateCallback($key, $forjado)->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
    })->with(['google', 'instagram', 'tiktok']);

    it('sem nonce: payload sem o nonce, mesmo selado com a chave do app, é recusado', function (string $key) {
        $user = createUserWithPermissions(['social_accounts.connect']);
        [$payload, $mode] = oauthStateOpen(oauthStateFor($user, $key));
        unset($payload['nonce']);
        stateFakeNetwork($key);

        stateCallback($key, oauthStateSeal($payload, $mode))->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
    })->with(['google', 'instagram', 'tiktok']);

    it('state antigo (apenas o uuid do workspace cifrado) é recusado', function (string $key) {
        $user = createUserWithPermissions(['social_accounts.connect']);
        stateFakeNetwork($key);

        stateCallback($key, encrypt($user->currentAccess->workspace->uuid))->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
    })->with(['google', 'instagram', 'tiktok']);

    it('sem state: error= e nada criado', function (string $key) {
        stateFakeNetwork($key);

        $this->get("/api/social-accounts/{$key}/callback?code=c")->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
    })->with(['google', 'instagram', 'tiktok']);

    it('de outro workspace: trocar o workspace_uuid do payload por outro workspace é recusado e nada é criado em nenhum', function (string $key) {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $vitima = createUserWithPermissions([]);
        [$payload, $mode] = oauthStateOpen(oauthStateFor($user, $key));
        $forjado = oauthStateSeal(array_merge($payload, ['workspace_uuid' => $vitima->currentAccess->workspace->uuid]), $mode);
        stateFakeNetwork($key);

        stateCallback($key, $forjado)->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
    })->with(['google', 'instagram', 'tiktok']);

    it('o iniciador saiu do workspace antes do callback: error= e nada é criado', function (string $key) {
        $dono = createUserWithPermissions(['social_accounts.connect']);
        $iniciador = createUserWithPermissions(['social_accounts.connect'], true, $dono->currentAccess->workspace);
        $state = oauthStateFor($iniciador, $key);
        Access::where('user_id', $iniciador->id)->where('workspace_id', $dono->currentAccess->workspace_id)->delete();
        stateFakeNetwork($key);

        stateCallback($key, $state)->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
    })->with(['google', 'instagram', 'tiktok']);

    it('state emitido para um workspace não conecta conta em outro: a conta cai no workspace do iniciador', function (string $key, string $platform) {
        $a = createUserWithPermissions(['social_accounts.connect']);
        $b = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($a, $key);
        stateFakeNetwork($key);

        stateCallback($key, $state)->assertRedirectContains('success=1');

        expect(stateAccounts()->where('platform', $platform)->sole()->workspace_id)->toBe($a->currentAccess->workspace_id)
            ->and(stateAccounts()->where('workspace_id', $b->currentAccess->workspace_id)->count())->toBe(0);
    })->with($redes);

});

describe('RF-7: state e nonce nunca vazam em log nem em resposta de erro', function () {

    it('callbacks rejeitados (replay, expirado, adulterado) não escrevem state/nonce no log nem no redirect', function (string $key) {
        $logs = tiktokCaptureLogs();
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, $key);
        [$payload] = oauthStateOpen($state);
        $nonce = $payload['nonce'];
        $estadoQueVaiExpirar = oauthStateFor($user, $key);
        [$payload2] = oauthStateOpen($estadoQueVaiExpirar);
        stateFakeNetwork($key);

        $respostas = [];
        $respostas[] = stateCallback($key, $state);            // válido
        $respostas[] = stateCallback($key, $state, 'code-2');  // replay
        $respostas[] = stateCallback($key, substr($state, 0, -3).'xyz'); // adulterado
        $this->travel(11)->minutes();
        $respostas[] = stateCallback($key, $estadoQueVaiExpirar); // expirado

        foreach ($respostas as $r) {
            $location = urldecode((string) $r->headers->get('Location'));
            expect($location)->not->toContain($state)->not->toContain($nonce)->not->toContain($estadoQueVaiExpirar)->not->toContain($payload2['nonce'])->not->toContain('state=');
        }
        expect($logs())->not->toContain($state)->not->toContain($nonce)->not->toContain($estadoQueVaiExpirar)->not->toContain($payload2['nonce']);
    })->with(['google', 'instagram', 'tiktok']);

    it('a falha na troca do code não devolve o state nem o nonce no redirect nem no log', function () {
        $logs = tiktokCaptureLogs();
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');
        [$payload] = oauthStateOpen($state);
        Http::fake(['open.tiktokapis.com/v2/oauth/token/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        $r = stateCallback('tiktok', $state);

        expect(urldecode($r->headers->get('Location')))->toContain('error=')->not->toContain($payload['nonce'])->not->toContain($state)
            ->and($logs())->not->toContain($payload['nonce'])->not->toContain($state)
            ->and(stateAccounts()->count())->toBe(0);
    });

    it('a mensagem de erro de state inválido é fixa e amigável, sem detalhes internos', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');
        Http::fake();
        stateCallback('tiktok', $state, 'a'); // consome (falha de rede fake), depois replay

        $r = stateCallback('tiktok', $state, 'b');

        $location = urldecode($r->headers->get('Location'));
        expect($location)->toContain('error=')->not->toContain('Exception')->not->toContain('nonce')->not->toContain('Cache');
    });

});

describe('state: uso único também quando a troca do code falha', function () {

    it('state consumido não pode ser reaproveitado para uma segunda tentativa (TikTok)', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');
        Http::fake(['open.tiktokapis.com/v2/oauth/token/*' => Http::response(['error' => 'invalid_grant'], 400)]);
        stateCallback('tiktok', $state);
        stateFakeNetwork('tiktok');

        stateCallback('tiktok', $state, 'code-2')->assertRedirectContains('error=');

        expect(stateAccounts()->count())->toBe(0);
        Http::assertNotSent(fn (Request $r) => ($r['code'] ?? null) === 'code-2');
    });

});
