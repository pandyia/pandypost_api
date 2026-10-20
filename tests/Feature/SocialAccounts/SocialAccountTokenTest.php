<?php

use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function expiredAccount(string $platform, array $overrides = []): SocialAccount
{
    return connectedSocialAccount(createUserWithPermissions([]), $platform, array_merge([
        'access_token' => 'token-velho',
        'refresh_token' => 'refresh-velho',
        'expires_at' => now()->subHour(),
    ], $overrides));
}

describe('getValidToken', function () {

    it('devolve o token atual quando ainda não expirou, sem chamar a plataforma', function () {
        Http::fake();
        $account = connectedSocialAccount(createUserWithPermissions([]), 'youtube');

        expect($account->getValidToken())->toBe('token-valido');
        Http::assertNothingSent();
    });

    it('renova o token do YouTube e grava access_token e expires_at', function () {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'novo-yt', 'expires_in' => 3600])]);
        $account = expiredAccount('youtube');

        expect($account->getValidToken())->toBe('novo-yt');

        $fresh = $account->fresh();
        expect($fresh->access_token)->toBe('novo-yt')
            ->and($fresh->expires_at->isFuture())->toBeTrue();
    });

    it('renova o token do Instagram e grava access_token e expires_at', function () {
        Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response(['access_token' => 'novo-ig', 'expires_in' => 5184000])]);
        $account = expiredAccount('instagram');

        expect($account->getValidToken())->toBe('novo-ig');

        $fresh = $account->fresh();
        expect($fresh->access_token)->toBe('novo-ig')
            ->and($fresh->expires_at->isFuture())->toBeTrue();
    });

    it('renova o token do TikTok e troca o refresh_token', function () {
        Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::response([
            'access_token' => 'novo-tt',
            'refresh_token' => 'novo-refresh-tt',
            'expires_in' => 86400,
        ])]);
        $account = expiredAccount('tiktok');

        expect($account->getValidToken())->toBe('novo-tt');

        $fresh = $account->fresh();
        expect($fresh->access_token)->toBe('novo-tt')
            ->and($fresh->refresh_token)->toBe('novo-refresh-tt')
            ->and($fresh->expires_at->isFuture())->toBeTrue();
    });

    it('YouTube sem refresh_token exige reconexão', function () {
        Http::fake();
        $account = expiredAccount('youtube', ['refresh_token' => null]);

        expect(fn () => $account->getValidToken())
            ->toThrow(Exception::class, 'Refresh token ausente. O usuário precisa reconectar a conta.');
        Http::assertNothingSent();
    });

    it('TikTok sem refresh_token exige reconexão', function () {
        Http::fake();
        $account = expiredAccount('tiktok', ['refresh_token' => null]);

        expect(fn () => $account->getValidToken())
            ->toThrow(Exception::class, 'Refresh token ausente. O usuário precisa reconectar a conta.');
    });

    it('falha na renovação do YouTube inclui o corpo da resposta na mensagem', function () {
        Http::fake(['oauth2.googleapis.com/token' => Http::response('invalid_grant', 400)]);
        $account = expiredAccount('youtube');

        expect(fn () => $account->getValidToken())
            ->toThrow(Exception::class, 'Não foi possível renovar o token do Google: invalid_grant');
    });

    it('falha na renovação do TikTok inclui o corpo da resposta na mensagem', function () {
        Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::response('refresh_token_expired', 400)]);
        $account = expiredAccount('tiktok');

        expect(fn () => $account->getValidToken())
            ->toThrow(Exception::class, 'Não foi possível renovar o token do TikTok: refresh_token_expired');
    });

    it('TikTok com resposta 200 sem access_token também falha', function () {
        Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::response(['error' => 'x'])]);
        $account = expiredAccount('tiktok');

        expect(fn () => $account->getValidToken())
            ->toThrow(Exception::class, 'Não foi possível renovar o token do TikTok: ');
    });

    it('falha na renovação do Instagram inclui o corpo da resposta na mensagem', function () {
        Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response('token_invalido', 400)]);
        $account = expiredAccount('instagram');

        expect(fn () => $account->getValidToken())
            ->toThrow(Exception::class, 'Não foi possível renovar o token do Instagram: token_invalido');
    });
});

describe('revokeToken', function () {

    it('revoga o token do YouTube no Google', function () {
        Http::fake();
        connectedSocialAccount(createUserWithPermissions([]), 'youtube')->revokeToken();

        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/revoke'
            && $request['token'] === 'token-valido');
    });

    it('revoga o token do TikTok', function () {
        Http::fake();
        connectedSocialAccount(createUserWithPermissions([]), 'tiktok')->revokeToken();

        Http::assertSent(fn ($request) => $request->url() === 'https://open.tiktokapis.com/v2/oauth/revoke/'
            && $request['token'] === 'token-valido');
    });

    it('Instagram não chama nenhuma API de revogação', function () {
        Http::fake();
        connectedSocialAccount(createUserWithPermissions([]), 'instagram')->revokeToken();

        Http::assertNothingSent();
    });

    it('sem access_token não chama a plataforma', function () {
        Http::fake();
        connectedSocialAccount(createUserWithPermissions([]), 'youtube', ['access_token' => ''])->revokeToken();

        Http::assertNothingSent();
    });
});
