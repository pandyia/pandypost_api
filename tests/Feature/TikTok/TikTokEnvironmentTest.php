<?php

use App\Exceptions\TikTokException;
use App\Models\ScheduledPost;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// D8: nada em código/config diferencia sandbox de produção; a privacidade permitida vem só do creator-info.

function tiktokSetEnv(string $env): void
{
    config(['app.env' => $env]);
    app()['env'] = $env;
}

describe('D8: APP_ENV não muda o comportamento do TikTok', function () {

    beforeEach(function () {
        $this->withoutMiddleware(ThrottleRequests::class);
        Bus::fake();
    });

    it('o creator-info devolve as mesmas opções em qualquer APP_ENV', function (string $env) {
        tiktokSetEnv($env);
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = tiktokAccount($user);
        tiktokFakeApi(['creator' => ['privacy_level_options' => ['SELF_ONLY', 'MUTUAL_FOLLOW_FRIENDS']]]);

        $this->withToken($user->test_token)->getJson("/api/social-accounts/{$account->uuid}/tiktok/creator-info")
            ->assertOk()
            ->assertJsonPath('data.privacy_level_options', ['SELF_ONLY', 'MUTUAL_FOLLOW_FRIENDS']);
    })->with(['local', 'testing', 'staging', 'production']);

    it('publicação pública é recusada quando o creator-info só permite SELF_ONLY, em qualquer APP_ENV (nunca rebaixa)', function (string $env) {
        tiktokSetEnv($env);
        $user = createUserWithPermissions(['posts.create']);
        $account = tiktokAccount($user);
        $post = tiktokPost($user, $account, tiktokMedia($user), ['privacy_level' => 'PUBLIC_TO_EVERYONE']);
        tiktokFakeApi(['creator' => ['privacy_level_options' => ['SELF_ONLY']]]);

        expect(fn () => tiktokUploadViaJob($account, $post))->toThrow(TikTokException::class);

        expect($post->fresh()->status)->toBe('failed');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/video/init/'));
    })->with(['local', 'testing', 'staging', 'production']);

    it('publicação pública passa quando o creator-info a permite (app auditado), em qualquer APP_ENV', function (string $env) {
        tiktokSetEnv($env);
        $user = createUserWithPermissions(['posts.create']);
        $account = tiktokAccount($user);
        $post = tiktokPost($user, $account, tiktokMedia($user), ['privacy_level' => 'PUBLIC_TO_EVERYONE']);
        tiktokFakeApi(['creator' => ['privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY']]]);

        app(TikTokService::class)->upload($account, $post);

        expect($post->fresh()->status)->toBe('processing');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/video/init/') && $r->data()['post_info']['privacy_level'] === 'PUBLIC_TO_EVERYONE');
    })->with(['local', 'testing', 'staging', 'production']);

    it('o escopo de publicação é exigido igualmente em qualquer APP_ENV', function (string $env) {
        tiktokSetEnv($env);
        $user = createUserWithPermissions(['posts.create']);
        $account = tiktokAccount($user, ['scopes' => null]);

        expect($account->canPublish())->toBeFalse();
    })->with(['local', 'testing', 'staging', 'production']);

    it('não há stub: nenhum ID tiktok_stub_ é gravado em nenhum APP_ENV', function (string $env) {
        tiktokSetEnv($env);
        $user = createUserWithPermissions(['posts.create']);
        $account = tiktokAccount($user);
        $post = tiktokPost($user, $account, tiktokMedia($user));
        tiktokFakeApi();

        app(TikTokService::class)->upload($account, $post);

        $fresh = ScheduledPost::withoutGlobalScopes()->find($post->id);
        expect($fresh->platform_post_id)->toBeNull()
            ->and((string) $fresh->container_id)->not->toContain('tiktok_stub')
            ->and($fresh->status)->not->toBe('published');
    })->with(['local', 'production']);

});
