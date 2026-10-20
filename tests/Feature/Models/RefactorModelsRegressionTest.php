<?php

use App\Enums\Exceptions\SocialAccountError;
use App\Exceptions\SocialAccountException;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('erros de domínio de SocialAccount', function () {

    it('refresh token ausente lança SocialAccountException com o código de domínio', function () {
        $account = connectedSocialAccount(createUserWithPermissions([]), 'youtube', [
            'refresh_token' => null,
            'expires_at' => now()->subHour(),
        ]);

        try {
            $account->getValidToken();
            $this->fail('Deveria lançar exceção.');
        } catch (SocialAccountException $e) {
            expect($e->getMessage())->toBe('Refresh token ausente. O usuário precisa reconectar a conta.')
                ->and(SocialAccountError::REFRESH_TOKEN_MISSING->httpCode())->toBe(401);
        }
    });

    it('falha ao renovar token lança SocialAccountException com a mesma mensagem de antes', function () {
        Http::fake(['graph.instagram.com/*' => Http::response('token_invalido', 400)]);
        $account = connectedSocialAccount(createUserWithPermissions([]), 'instagram', [
            'expires_at' => now()->subHour(),
        ]);

        expect(fn () => $account->getValidToken())
            ->toThrow(SocialAccountException::class, 'Não foi possível renovar o token do Instagram: token_invalido');
    });
});

describe('Plan oculta id interno nas respostas de billing', function () {

    it('admin: listagem e detalhe de plano não expõem o id numérico', function () {
        config(['services.stripe.enabled' => false]);
        $admin = createSuperAdmin();
        $uuid = $this->withToken($admin->test_token)->postJson('/api/admin/plans', ['name' => 'Pro'])->json('data.id');

        $this->withToken($admin->test_token)->getJson("/api/admin/plans/{$uuid}")
            ->assertOk()->assertJsonPath('data.id', $uuid);

        $this->withToken($admin->test_token)->getJson('/api/admin/plans')
            ->assertOk()->assertJsonPath('data.0.id', $uuid);

        expect(Plan::first()->toArray())->not->toHaveKey('id');
    });
});
