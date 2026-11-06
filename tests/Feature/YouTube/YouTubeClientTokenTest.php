<?php

use App\Exceptions\YouTubeException;
use App\Models\Notification;
use App\Services\YouTubeService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function youtubeAuthenticatedClient($account)
{
    $method = new ReflectionMethod(YouTubeService::class, 'getAuthenticatedClient');

    return $method->invoke(app(YouTubeService::class), $account);
}

function youtubeJsonToken(int $createdAgo, int $expiresIn = 3600): string
{
    return json_encode(['access_token' => 'ya29.json', 'created' => time() - $createdAgo, 'expires_in' => $expiresIn]);
}

describe('token do cliente YouTube (caracterização)', function () {

    it('token em JSON ainda válido no SDK não renova', function () {
        $user = createUserWithPermissions([]);
        $account = youtubeAccount($user, ['access_token' => youtubeJsonToken(60)]);
        $history = youtubeFakeSdk([]);

        $client = youtubeAuthenticatedClient($account);

        expect($history)->toHaveCount(0)
            ->and($client->getAccessToken()['access_token'])->toBe('ya29.json');
    });

    it('token em JSON vencido com refresh_token renova e grava access_token e expires_at', function () {
        $user = createUserWithPermissions([]);
        $account = youtubeAccount($user, ['access_token' => youtubeJsonToken(7200), 'expires_at' => now()->subHour()]);
        youtubeFakeSdk([youtubeRefreshResponse('ya29.novo')]);

        youtubeAuthenticatedClient($account);

        $fresh = $account->fresh();
        expect($fresh->access_token)->toBe('ya29.novo')
            ->and($fresh->expires_at->isFuture())->toBeTrue();
    });

    it('sem refresh_token e vencido: notifica a desconexão e lança', function () {
        $user = createUserWithPermissions([]);
        $account = youtubeAccount($user, ['access_token' => youtubeJsonToken(7200), 'refresh_token' => null]);
        youtubeFakeSdk([]);

        expect(fn () => youtubeAuthenticatedClient($account))->toThrow(Exception::class, 'Token expirado e sem refresh_token disponível.');
        expect(Notification::where('type', 'social_account_disconnected')->count())->toBe(1);
    });

    it('formato real guardado pela conexão (string do Socialite): vale o expires_at da conta, sem renovar à toa', function () {
        $user = createUserWithPermissions([]);
        $account = youtubeAccount($user, ['access_token' => 'ya29.socialite', 'expires_at' => now()->addMinutes(50)]);
        $history = youtubeFakeSdk([]);

        $client = youtubeAuthenticatedClient($account);

        expect($history)->toHaveCount(0)
            ->and($client->getAccessToken()['access_token'])->toBe('ya29.socialite');
    });

    it('string do Socialite vencida pelo expires_at renova e grava o novo token', function () {
        $user = createUserWithPermissions([]);
        $account = youtubeAccount($user, ['access_token' => 'ya29.socialite', 'expires_at' => now()->subMinute()]);
        $history = youtubeFakeSdk([youtubeRefreshResponse('ya29.novo')]);

        $client = youtubeAuthenticatedClient($account);

        expect($history)->toHaveCount(1)
            ->and($client->getAccessToken()['access_token'])->toBe('ya29.novo')
            ->and($account->fresh()->access_token)->toBe('ya29.novo')
            ->and($account->fresh()->expires_at->isFuture())->toBeTrue();
    });

    it('token válido pelo expires_at e sem refresh_token ainda publica (antes lançava: o SDK dava a string como vencida)', function () {
        $user = createUserWithPermissions([]);
        $account = youtubeAccount($user, ['access_token' => 'ya29.socialite', 'refresh_token' => null, 'expires_at' => now()->addMinutes(50)]);
        youtubeFakeSdk([]);

        $client = youtubeAuthenticatedClient($account);

        expect($client->getAccessToken()['access_token'])->toBe('ya29.socialite')
            ->and(Notification::where('type', 'social_account_disconnected')->count())->toBe(0);
    });

    it('refresh que falha lança YouTubeException e não grava nada (antes seguia em silêncio com o token vencido)', function () {
        $user = createUserWithPermissions([]);
        $account = youtubeAccount($user, ['access_token' => 'ya29.socialite', 'expires_at' => now()->subMinute()]);
        youtubeFakeSdk([new PsrResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_grant']))]);

        try {
            youtubeAuthenticatedClient($account);
            $this->fail('deveria lançar YouTubeException');
        } catch (YouTubeException $e) {
            expect($e->getMessage())->toBe('Não foi possível renovar o acesso à conta do YouTube. Reconecte a conta YouTube.')
                ->and($e->getMessage())->not->toContain('invalid_grant');
        }

        expect($account->fresh()->access_token)->toBe('ya29.socialite');
    });
});
