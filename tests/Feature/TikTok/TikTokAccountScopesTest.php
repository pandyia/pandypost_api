<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Services\OAuthProviders\TikTokOAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config([
        'services.tiktok.client_key' => 'test_client_key',
        'services.tiktok.client_secret' => 'test_client_secret',
        'services.tiktok.redirect' => 'https://app.pandypost.com/api/social-accounts/tiktok/callback',
    ]);
});

/** Normaliza o campo `scopes` (string "a,b" ou array) para texto, sem presumir o formato de armazenamento. */
function tiktokScopesAsText(SocialAccount $account): string
{
    $scopes = $account->fresh()->scopes;

    return is_array($scopes) ? implode(',', $scopes) : (string) $scopes;
}

describe('Fase 1: colunas em social_accounts', function () {

    it('tem a coluna username', function () {
        expect(Schema::hasColumn('social_accounts', 'username'))->toBeTrue();
    });

    it('tem a coluna scopes (RF-12)', function () {
        expect(Schema::hasColumn('social_accounts', 'scopes'))->toBeTrue();
    });

    it('scopes e username são gravados e relidos pelo model (fillable)', function () {
        $user = createUserWithPermissions([]);
        $account = tiktokAccount($user, ['username' => 'meu_canal']);

        expect($account->fresh()->username)->toBe('meu_canal')
            ->and(tiktokScopesAsText($account))->toContain('video.publish');
    });

});

describe('RF-1: OAuth do TikTok pede os escopos de publicação', function () {

    it('a URL de autorização inclui video.publish e video.upload, sem perder os escopos de leitura', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);

        $url = (new TikTokOAuthProvider)->getRedirectUrl($user);

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $scopes = explode(',', $query['scope']);

        expect($url)->toStartWith('https://www.tiktok.com/v2/auth/authorize/')
            ->and($scopes)->toContain('video.publish', 'video.upload')
            ->and($scopes)->toContain('user.info.basic', 'user.info.profile', 'user.info.stats', 'video.list');
    });

});

describe('RF-11/RF-12: callback grava scopes e username', function () {

    it('syncAccount grava os escopos concedidos (campo scope do token) e o username', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response([
                'access_token' => 'access-novo',
                'refresh_token' => 'refresh-novo',
                'open_id' => 'open-id-1',
                'expires_in' => 86400,
                'scope' => 'user.info.basic,user.info.profile,video.upload,video.publish',
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => [
                'display_name' => 'Criador', 'avatar_url' => 'https://x.test/a.jpg', 'username' => 'criador_tt',
            ]]]),
        ]);

        $account = (new TikTokOAuthProvider)->syncAccount($user, 'codigo');

        $stored = SocialAccount::withoutGlobalScopes()->find($account->id);
        expect($stored->username)->toBe('criador_tt')
            ->and(tiktokScopesAsText($stored))->toContain('video.publish')->toContain('video.upload')
            ->and($stored->canPublish())->toBeTrue();
    });

    it('reconectar uma conta antiga atualiza os escopos (updateOrCreate)', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $old = tiktokAccount($user, ['platform_id' => 'open-id-1', 'scopes' => 'user.info.basic']);
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response([
                'access_token' => 'a', 'refresh_token' => 'r', 'open_id' => 'open-id-1', 'expires_in' => 86400,
                'scope' => 'user.info.basic,video.upload,video.publish',
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'X']]]),
        ]);

        (new TikTokOAuthProvider)->syncAccount($user, 'codigo');

        expect(SocialAccount::withoutGlobalScopes()->where('platform', 'tiktok')->count())->toBe(1)
            ->and($old->fresh()->canPublish())->toBeTrue();
    });

    it('se o token não traz scope, a conta fica sem permissão de publicar', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response([
                'access_token' => 'a', 'refresh_token' => 'r', 'open_id' => 'open-id-2', 'expires_in' => 86400,
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'X']]]),
        ]);

        $account = (new TikTokOAuthProvider)->syncAccount($user, 'codigo');

        expect($account->fresh()->canPublish())->toBeFalse();
    });

});

describe('can_publish é genérico (D6)', function () {

    it('Platform::publishScope() do TikTok é video.publish', function () {
        expect(Platform::TIKTOK->publishScope())->toBe('video.publish');
    });

    it('TikTok com video.publish pode publicar', function () {
        $account = tiktokAccount(createUserWithPermissions([]));

        expect($account->canPublish())->toBeTrue();
    });

    it('TikTok sem video.publish (só leitura) não pode publicar', function () {
        $account = tiktokAccount(createUserWithPermissions([]), ['scopes' => 'user.info.basic,video.list']);

        expect($account->canPublish())->toBeFalse();
    });

    it('TikTok com scopes nulo (conta conectada antes da spec) não pode publicar', function () {
        $account = tiktokAccount(createUserWithPermissions([]), ['scopes' => null]);

        expect($account->canPublish())->toBeFalse();
    });

    it('rede cujo publishScope() é null pode publicar mesmo com scopes nulo', function () {
        $user = createUserWithPermissions([]);
        $semExigencia = collect(Platform::cases())->filter(fn (Platform $p) => $p->publishScope() === null);

        expect($semExigencia)->not->toBeEmpty('ao menos uma rede deve ter publishScope() null (hoje YouTube e Instagram)');

        foreach ($semExigencia as $platform) {
            $account = connectedSocialAccount($user, $platform->value, ['scopes' => null]);
            expect($account->canPublish())->toBeTrue("{$platform->value} sem scope exigido deve publicar");
        }
    });

    it('a decisão vem de Platform::publishScope(), não de um nome de escopo fixo no TikTok', function () {
        $user = createUserWithPermissions([]);

        foreach (Platform::cases() as $platform) {
            $required = $platform->publishScope();
            $without = connectedSocialAccount($user, $platform->value, ['scopes' => 'outro.escopo']);
            $with = connectedSocialAccount($user, $platform->value, ['scopes' => 'outro.escopo,'.($required ?? 'qualquer')]);

            expect($without->canPublish())->toBe($required === null);
            expect($with->canPublish())->toBeTrue();
        }
    });

});

describe('can_publish na SocialAccountResource', function () {

    it('lista expõe can_publish true/false por conta e nunca expõe tokens', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $ok = tiktokAccount($user, ['nickname' => 'Com escopo']);
        $antiga = tiktokAccount($user, ['nickname' => 'Antiga', 'scopes' => null]);
        $youtube = connectedSocialAccount($user, 'youtube', ['nickname' => 'YT', 'scopes' => null]);

        $response = $this->withToken($user->test_token)->getJson('/api/social-accounts')->assertOk();
        $byNickname = collect($response->json('data'))->keyBy('nickname');

        expect($byNickname['Com escopo']['can_publish'])->toBeTrue()
            ->and($byNickname['Antiga']['can_publish'])->toBeFalse()
            ->and($byNickname['YT']['can_publish'])->toBeTrue()
            ->and($response->getContent())->not->toContain('token-valido')->not->toContain('refresh-valido');
    });

});
