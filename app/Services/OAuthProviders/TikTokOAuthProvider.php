<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

class TikTokOAuthProvider implements OAuthProviderInterface
{
    public function getRedirectUrl(?User $user = null): string
    {
        $workspace = $user?->currentAccess?->workspace;

        if (!$workspace) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        $state = encrypt($workspace->uuid);

        $params = [
            'client_key' => config('services.tiktok.client_key'),
            'scope' => 'user.info.basic,video.publish,video.upload',
            'response_type' => 'code',
            'redirect_uri' => config('services.tiktok.redirect'),
            'state' => $state,
        ];

        return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query($params);
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

        if (!$code) {
            throw SocialAccountException::authFailed('TikTok');
        }

        $tokenResponse = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.tiktok.redirect'),
        ])->json();

        $accessToken = $tokenResponse['access_token'] ?? null;
        $refreshToken = $tokenResponse['refresh_token'] ?? null;
        $openId = $tokenResponse['open_id'] ?? null;
        $expiresIn = $tokenResponse['expires_in'] ?? 86400;

        if (!$accessToken || !$openId) {
            throw SocialAccountException::authFailed('TikTok');
        }

        $profileResponse = Http::withToken($accessToken)
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,union_id,avatar_url,display_name',
            ])->json();

        $userData = $profileResponse['data']['user'] ?? [];
        $nickname = $userData['display_name'] ?? 'TikTok User';
        $avatar = $userData['avatar_url'] ?? null;

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => 'tiktok',
                'platform_id' => $openId,
            ],
            [
                'user_id' => $userId,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds($expiresIn),
                'nickname' => $nickname,
                'avatar' => $avatar,
            ]
        );
    }
}
