<?php

use App\Exceptions\TikTokException;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Models\Notification;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const TIKTOK_SECRET_TOKEN = 'TOKEN-SECRETO-ABC123';
const TIKTOK_SECRET_REFRESH = 'REFRESH-SECRETO-XYZ789';
const TIKTOK_SECRET_BODY = 'CORPO-CRU-DO-TIKTOK-777';

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
});

describe('spec 011: creator-info (isolamento e vazamento)', function () {

    it('usuário de outro workspace não alcança o creator-info nem usa o token da conta alheia', function () {
        $dono = createUserWithPermissions(['social_accounts.view']);
        $conta = tiktokAccount($dono, ['access_token' => TIKTOK_SECRET_TOKEN]);
        $atacante = createUserWithPermissions(['social_accounts.view']);
        tiktokFakeApi();

        // Controle: o dono alcança a própria conta.
        $this->withToken($dono->test_token)->getJson("/api/social-accounts/{$conta->uuid}/tiktok/creator-info")->assertOk();
        Http::assertSentCount(1);

        // O guard do Sanctum guarda o usuário da 1ª chamada; sem isto a 2ª ainda iria como o dono.
        $this->app['auth']->forgetGuards();
        $response = $this->withToken($atacante->test_token)->getJson("/api/social-accounts/{$conta->uuid}/tiktok/creator-info");

        $response->assertNotFound();
        Http::assertSentCount(1);
        expect($response->getContent())->not->toContain(TIKTOK_SECRET_TOKEN);
    });

    it('a resposta do creator-info nunca contém access_token nem refresh_token', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $conta = tiktokAccount($user, ['access_token' => TIKTOK_SECRET_TOKEN, 'refresh_token' => TIKTOK_SECRET_REFRESH]);
        tiktokFakeApi();

        $content = $this->withToken($user->test_token)->getJson("/api/social-accounts/{$conta->uuid}/tiktok/creator-info")->assertOk()->getContent();

        expect($content)->not->toContain(TIKTOK_SECRET_TOKEN)->not->toContain(TIKTOK_SECRET_REFRESH);
    });

    it('erro do TikTok no creator-info não devolve o body cru nem o token', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $conta = tiktokAccount($user, ['access_token' => TIKTOK_SECRET_TOKEN]);
        tiktokFakeApi(['creator_response' => Http::response([
            'data' => [], 'error' => ['code' => 'spam_risk_too_many_posts', 'message' => TIKTOK_SECRET_BODY, 'log_id' => TIKTOK_SECRET_BODY],
        ], 429)]);

        $content = $this->withToken($user->test_token)->getJson("/api/social-accounts/{$conta->uuid}/tiktok/creator-info")->getContent();

        expect($content)->not->toContain(TIKTOK_SECRET_BODY)->not->toContain(TIKTOK_SECRET_TOKEN);
    });

    it('o erro do TikTok no creator-info é um erro de domínio (rota existe, resposta 4xx/5xx que não é 404)', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $conta = tiktokAccount($user);
        tiktokFakeApi(['creator_response' => Http::response(['data' => [], 'error' => ['code' => 'spam_risk_too_many_posts', 'message' => 'x']], 429)]);

        $status = $this->withToken($user->test_token)->getJson("/api/social-accounts/{$conta->uuid}/tiktok/creator-info")->status();

        expect($status)->toBeGreaterThanOrEqual(400)->not->toBe(404);
    });

    it('a listagem de contas (can_publish) não expõe tokens nem scopes crus de outro workspace', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        tiktokAccount(createUserWithPermissions([]), ['nickname' => 'Alheia', 'access_token' => TIKTOK_SECRET_TOKEN]);
        tiktokAccount($user, ['nickname' => 'Minha', 'access_token' => TIKTOK_SECRET_TOKEN, 'refresh_token' => TIKTOK_SECRET_REFRESH]);

        $response = $this->withToken($user->test_token)->getJson('/api/social-accounts')->assertOk();

        expect($response->json('data'))->toHaveCount(1)
            ->and($response->getContent())->not->toContain(TIKTOK_SECRET_TOKEN)->not->toContain(TIKTOK_SECRET_REFRESH)->not->toContain('Alheia');
    });

});

describe('spec 011: token e body cru nunca em error_message, notificação nem log', function () {

    it('falha no init: nem error_message, nem notificação, nem log carregam o token ou o body do TikTok', function () {
        $logs = tiktokCaptureLogs();
        $user = createUserWithPermissions(['posts.create', 'posts.view']);
        $conta = tiktokAccount($user, ['access_token' => TIKTOK_SECRET_TOKEN, 'refresh_token' => TIKTOK_SECRET_REFRESH]);
        $post = tiktokPost($user, $conta, tiktokMedia($user));
        tiktokFakeApi(['init_response' => Http::response([
            'error' => ['code' => 'file_format_check_failed', 'message' => TIKTOK_SECRET_BODY, 'log_id' => TIKTOK_SECRET_BODY],
            'echo' => 'Authorization: Bearer '.TIKTOK_SECRET_TOKEN,
        ], 400)]);

        expect(fn () => tiktokUploadViaJob($conta, $post))->toThrow(TikTokException::class);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed');

        $resource = $this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk()->getContent();
        $notifs = json_encode(Notification::all()->toArray());

        foreach ([TIKTOK_SECRET_TOKEN, TIKTOK_SECRET_REFRESH, TIKTOK_SECRET_BODY] as $segredo) {
            expect($fresh->error_message)->not->toContain($segredo)
                ->and($resource)->not->toContain($segredo)
                ->and($notifs)->not->toContain($segredo)
                ->and($logs())->not->toContain($segredo);
        }
    });

    it('falha no envio do chunk: o body da resposta do upload não vaza para error_message, listagem nem log', function () {
        $logs = tiktokCaptureLogs();
        $user = createUserWithPermissions(['posts.create', 'posts.view']);
        $conta = tiktokAccount($user, ['access_token' => TIKTOK_SECRET_TOKEN]);
        $post = tiktokPost($user, $conta, tiktokMedia($user));
        tiktokFakeApi(['put_status' => 403, 'put_body' => TIKTOK_SECRET_BODY]);

        expect(fn () => tiktokUploadViaJob($conta, $post))->toThrow(TikTokException::class);

        $resource = $this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk()->getContent();
        expect($post->fresh()->error_message)->not->toContain(TIKTOK_SECRET_BODY)
            ->and($resource)->not->toContain(TIKTOK_SECRET_BODY)
            ->and($logs())->not->toContain(TIKTOK_SECRET_BODY)->not->toContain(TIKTOK_SECRET_TOKEN);
    });

    it('job de status: fail_reason e body do TikTok não vazam para error_message, listagem nem log', function () {
        $logs = tiktokCaptureLogs();
        $user = createUserWithPermissions(['posts.create', 'posts.view']);
        $conta = tiktokAccount($user, ['access_token' => TIKTOK_SECRET_TOKEN]);
        $post = tiktokPost($user, $conta, tiktokMedia($user), [], ['status' => 'processing', 'container_id' => 'pub1']);
        Http::fake(['open.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response([
            'data' => ['status' => 'FAILED', 'fail_reason' => TIKTOK_SECRET_BODY],
            'error' => ['code' => 'ok', 'message' => ''],
        ])]);
        Http::preventStrayRequests();

        $job = new CheckTikTokPostStatusJob($post, $conta, 'pub1');
        $job->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        $resource = $this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk()->getContent();
        expect($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->not->toContain(TIKTOK_SECRET_BODY)
            ->and($resource)->not->toContain(TIKTOK_SECRET_BODY)
            ->and(json_encode(Notification::all()->toArray()))->not->toContain(TIKTOK_SECRET_BODY)
            ->and($logs())->not->toContain(TIKTOK_SECRET_TOKEN);
    });

    it('sucesso: o token não aparece em nenhum log da publicação', function () {
        $logs = tiktokCaptureLogs();
        $user = createUserWithPermissions(['posts.create']);
        $conta = tiktokAccount($user, ['access_token' => TIKTOK_SECRET_TOKEN, 'refresh_token' => TIKTOK_SECRET_REFRESH]);
        $post = tiktokPost($user, $conta, tiktokMedia($user));
        tiktokFakeApi();

        app(TikTokService::class)->upload($conta, $post);

        expect($logs())->not->toContain(TIKTOK_SECRET_TOKEN)->not->toContain(TIKTOK_SECRET_REFRESH);
    });

    it('renovação de token que falha não vaza o body do TikTok nem o refresh token no error_message', function () {
        $logs = tiktokCaptureLogs();
        $user = createUserWithPermissions(['posts.create']);
        $conta = tiktokAccount($user, ['expires_at' => now()->subHour(), 'refresh_token' => TIKTOK_SECRET_REFRESH]);
        $post = tiktokPost($user, $conta, tiktokMedia($user));
        tiktokFakeApi(['token_response' => Http::response(['error' => 'invalid_grant', 'error_description' => TIKTOK_SECRET_BODY], 400)]);

        try {
            tiktokUploadViaJob($conta, $post);
        } catch (Throwable) {
        }

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->not->toContain(TIKTOK_SECRET_BODY)->not->toContain(TIKTOK_SECRET_REFRESH)
            ->and($logs())->not->toContain(TIKTOK_SECRET_REFRESH);
    });

});
