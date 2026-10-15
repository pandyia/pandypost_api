<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Login Kit (web) + Display API.
 * Escopos de leitura para o analytics; publicação continua fora (specs/003).
 */
class TikTokOAuthProvider implements OAuthProviderInterface
{
    private const AUTHORIZE_URL = 'https://www.tiktok.com/v2/auth/authorize/';

    private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

    private const USER_INFO_URL = 'https://open.tiktokapis.com/v2/user/info/';

    private const SCOPES = 'user.info.basic,user.info.profile,user.info.stats,video.list';

    public function getRedirectUrl(?User $user = null): string
    {
        $workspace = $user?->currentAccess?->workspace;

        if (! $workspace || ! config('services.tiktok.client_key')) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_key' => config('services.tiktok.client_key'),
            'scope' => self::SCOPES,
            'response_type' => 'code',
            'redirect_uri' => config('services.tiktok.redirect'),
            'state' => encrypt($workspace->uuid),
        ]);
    }

    public function syncAccount(User|Workspace $context, ?string $code = null): SocialAccount
    {
        if ($context instanceof Workspace) {
            $workspaceId = $context->id;
            $userId = $context->accesses()->first()?->user_id;
        } else {
            $workspaceId = $context->currentAccess->workspace_id;
            $userId = $context->id;
        }

        if (! $code) {
            throw SocialAccountException::authFailed('TikTok');
        }

        $token = $this->exchangeCode($code);

        if (empty($token['access_token']) || empty($token['open_id'])) {
            throw SocialAccountException::oauthTokenExchangeFailed();
        }

        $profile = Http::withToken($token['access_token'])
            ->get(self::USER_INFO_URL, ['fields' => 'open_id,display_name,avatar_url'])
            ->json('data.user', []);

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => 'tiktok',
                'platform_id' => $token['open_id'],
            ],
            [
                'user_id' => $userId,
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($token['expires_in'] ?? 86400),
                'nickname' => $profile['display_name'] ?? 'TikTok',
                'avatar' => $profile['avatar_url'] ?? null,
            ]
        );
    }

    private function exchangeCode(string $code): array
    {
        return Http::asForm()->post(self::TOKEN_URL, [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.tiktok.redirect'),
        ])->json() ?? [];
    }
}
