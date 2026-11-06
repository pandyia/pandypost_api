<?php

namespace App\Services\YouTube;

use App\Contracts\PlatformTokenApiInterface;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

/**
 * Endpoint OAuth do Google. O upload usa o SDK do Google direto (YouTubeService): embrulhá-lo aqui seria uma camada 1:1.
 */
class YouTubeApi implements PlatformTokenApiInterface
{
    private const OAUTH_BASE_URL = 'https://oauth2.googleapis.com';

    public function refreshAccountToken(SocialAccount $account): array
    {
        if (! $account->refresh_token) {
            throw SocialAccountException::refreshTokenMissing();
        }

        $response = Http::asForm()->post(self::OAUTH_BASE_URL.'/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        if (! $response->successful()) {
            throw SocialAccountException::tokenRefreshFailed('Google');
        }

        return [
            'access_token' => $response->json('access_token'),
            'expires_in' => $response->json('expires_in'),
        ];
    }

    public function revokeAccountToken(SocialAccount $account): void
    {
        Http::post(self::OAUTH_BASE_URL.'/revoke', ['token' => $account->access_token]);
    }
}
