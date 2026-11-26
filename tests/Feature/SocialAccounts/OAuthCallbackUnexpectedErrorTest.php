<?php

use App\Models\SocialAccount;
use App\Services\SocialAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Caracterização — ramo Throwable do callback OAuth (spec 022, task 2.0)
|--------------------------------------------------------------------------
|
| Trava o comportamento do único ramo do callback ainda sem teste antes da
| refatoração (RF-7): erro inesperado (não-BaseException) durante o sync →
| redirect com a mensagem genérica e Log::warning contendo SÓ a classe da
| exceção, nunca a mensagem (que pode trazer SQL, token ou URL de terceiro).
*/

beforeEach(function () {
    config(['app.frontend_url' => 'https://front.test']);
});

describe('callback OAuth com erro inesperado (caracterização)', function () {

    it('redireciona com a mensagem genérica, loga só a classe da exceção e não cria conta', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');

        // O partial mock não roda o construtor; a plataforma 'tiktok' é suportada de verdade.
        $this->partialMock(SocialAccountService::class, function ($mock) {
            $mock->shouldReceive('ensurePlatformSupported')->once();
            $mock->shouldReceive('syncAccount')
                ->once()
                ->andThrow(new RuntimeException('detalhe-sensivel: token xyz'));
        });

        Log::spy();

        $this->get('/api/social-accounts/tiktok/callback?code=code-1&state='.urlencode($state))
            ->assertRedirect(
                'https://front.test/social-accounts/callback?error='
                .urlencode('Não foi possível conectar a conta. Tente novamente.')
            );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message) => $message === 'Falha inesperada no callback OAuth (tiktok, RuntimeException).');

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });
});
