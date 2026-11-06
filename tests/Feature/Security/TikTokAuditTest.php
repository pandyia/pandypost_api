<?php

use App\Exceptions\TikTokException;
use App\Models\SocialAccount;
use App\Services\OAuthProviders\TikTokOAuthProvider;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

const TT_AUDIT_SIGNED_QUERY = 'x-signature=ASSINATURA-SECRETA-999';

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config([
        'services.tiktok.client_key' => 'test_client_key',
        'services.tiktok.client_secret' => 'test_client_secret',
        'services.tiktok.redirect' => 'https://app.pandypost.com/api/social-accounts/tiktok/callback',
        'app.frontend_url' => 'https://front.test',
    ]);
});

describe('spec 011 auditoria: upload_url devolvida pelo TikTok', function () {

    it('VULN-TT-01: não envia o vídeo para um upload_url fora de https/domínio do TikTok (SSRF/exfiltração)', function () {
        $user = createUserWithPermissions(['posts.create']);
        $conta = tiktokAccount($user);
        $post = tiktokPost($user, $conta, tiktokMedia($user));
        tiktokFakeApi(['init_response' => Http::response([
            'data' => ['publish_id' => 'p1', 'upload_url' => 'http://169.254.169.254/latest/meta-data/'],
            'error' => ['code' => 'ok', 'message' => ''],
        ])]);
        Http::fake(['169.254.169.254/*' => Http::response('', 201)]);

        try {
            app(TikTokService::class)->upload($conta, $post);
        } catch (Throwable) {
        }

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254.169.254'));
    });

});

describe('spec 011 auditoria: vazamento por exceção que não é de domínio', function () {

    it('VULN-TT-02: falha de conexão no PUT do chunk não grava a URL assinada do upload em error_message nem na listagem', function () {
        $user = createUserWithPermissions(['posts.create', 'posts.view']);
        $conta = tiktokAccount($user);
        $post = tiktokPost($user, $conta, tiktokMedia($user));
        tiktokFakeApi(['init_response' => Http::response([
            'data' => ['publish_id' => 'p1', 'upload_url' => 'https://open-upload.tiktokapis.com/video/abc?'.TT_AUDIT_SIGNED_QUERY],
            'error' => ['code' => 'ok', 'message' => ''],
        ])]);
        Http::fake(['open-upload.tiktokapis.com/*' => fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out for https://open-upload.tiktokapis.com/video/abc?'.TT_AUDIT_SIGNED_QUERY
        )]);

        expect(fn () => tiktokUploadViaJob($conta, $post))->toThrow(TikTokException::class);

        $lista = $this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk()->getContent();
        expect($post->fresh()->error_message)->not->toContain('ASSINATURA-SECRETA-999')
            ->and($lista)->not->toContain('ASSINATURA-SECRETA-999');
    });

});

describe('spec 011 auditoria: OAuth do TikTok', function () {

    it('VULN-TT-03 (OAUTH-01): o state do TikTok não pode ser reutilizado nem forjável com o uuid do workspace', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $url = app(TikTokOAuthProvider::class)->getRedirectUrl($user);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'open_id' => 'oid', 'scope' => TIKTOK_SCOPES_OK]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'X']]]),
        ]);

        $this->getJson('/api/social-accounts/tiktok/callback?code=c1&state='.urlencode($q['state']))->assertRedirect();
        $segunda = $this->getJson('/api/social-accounts/tiktok/callback?code=c2&state='.urlencode($q['state']));

        expect($segunda->headers->get('Location'))->toContain('error=');
    });

    it('regressão: state que não é um payload cifrado pelo app é rejeitado e nada é criado', function () {
        Http::fake();
        $workspaceUuid = createUserWithPermissions([])->currentAccess->workspace->uuid;

        $r = $this->getJson('/api/social-accounts/tiktok/callback?code=c&state='.$workspaceUuid);

        expect($r->headers->get('Location'))->toContain('error=')
            ->and(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('regressão: troca de code falha sem devolver segredos no redirect nem gravar conta', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $url = app(TikTokOAuthProvider::class)->getRedirectUrl($user);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        Http::fake(['open.tiktokapis.com/v2/oauth/token/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'CORPO-CRU-SECRETO'], 400)]);

        $r = $this->getJson('/api/social-accounts/tiktok/callback?code=ruim&state='.urlencode($q['state']));

        expect(urldecode($r->headers->get('Location')))->not->toContain('CORPO-CRU-SECRETO')->not->toContain('test_client_secret')
            ->and(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });

    it('regressão: client_secret e tokens não aparecem na URL de autorização', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $url = app(TikTokOAuthProvider::class)->getRedirectUrl($user);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        expect($url)->not->toContain('test_client_secret')
            ->and($q['scope'])->toContain('video.publish')->and($q['response_type'])->toBe('code');
    });

});

describe('spec 011 auditoria: SocialAccount (scopes/username)', function () {

    it('regressão: Resource e toArray não expõem tokens, id, scopes crus nem username', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        tiktokAccount($user, ['access_token' => 'TOK-AAA', 'refresh_token' => 'REF-BBB']);

        $content = $this->withToken($user->test_token)->getJson('/api/social-accounts')->assertOk()->getContent();
        $arr = SocialAccount::withoutGlobalScopes()->first()->toArray();

        expect($content)->not->toContain('TOK-AAA')->not->toContain('REF-BBB')->not->toContain('video.upload')
            ->and($arr)->not->toHaveKeys(['id', 'access_token', 'refresh_token']);
    });

    it('regressão: auditoria (owen-it) da conta TikTok não grava tokens', function () {
        $user = createUserWithPermissions([]);
        $conta = tiktokAccount($user, ['access_token' => 'TOK-AAA', 'refresh_token' => 'REF-BBB']);
        $conta->update(['access_token' => 'TOK-NOVO', 'nickname' => 'Outro']);

        $audits = json_encode(Audit::all()->toArray());

        expect($audits)->not->toContain('TOK-AAA')->not->toContain('TOK-NOVO')->not->toContain('REF-BBB');
    });

    it('regressão: scopes é comparado por item exato (substring não concede publicação)', function () {
        $user = createUserWithPermissions([]);
        $conta = tiktokAccount($user, ['scopes' => 'video.publish.fake,xvideo.upload']);

        expect($conta->canPublish())->toBeFalse();
    });

});

describe('spec 011 auditoria: creator-info e permissões', function () {

    it('regressão: rota creator-info exige permission:social_accounts.view via middleware', function () {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => str_contains($r->uri(), 'tiktok/creator-info'));

        expect(collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_contains($m, 'permission:social_accounts.view')))->toBeTrue();
    });

    it('regressão: conta de outra plataforma no mesmo workspace dá 404 sem chamar o TikTok', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $yt = connectedSocialAccount($user, 'youtube');
        Http::fake();

        $this->withToken($user->test_token)->getJson("/api/social-accounts/{$yt->uuid}/tiktok/creator-info")->assertNotFound();
        Http::assertNothingSent();
    });

    it('regressão: erro inesperado do TikTok no creator-info não devolve stack nem URL', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $conta = tiktokAccount($user);
        config(['app.debug' => false]); // produção
        Http::fake(['open.tiktokapis.com/*' => fn () => throw new ConnectionException('cURL error 6 for https://open.tiktokapis.com/v2/post/publish/creator_info/query/')]);

        $c = $this->withToken($user->test_token)->getJson("/api/social-accounts/{$conta->uuid}/tiktok/creator-info")->getContent();

        expect($c)->not->toContain('open.tiktokapis.com')->not->toContain('vendor/');
    });

});
