<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

class InstagramOAuthProvider implements OAuthProviderInterface
{
    public function getRedirectUrl(?User $user = null): string
    {
        $workspace = $user?->currentAccess?->workspace;

        if (!$workspace) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        $state = encrypt($workspace->uuid);

        $params = [
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => config('services.meta.redirect'),
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_content_publish,instagram_business_manage_messages',
            'state' => $state,
        ];

        $query = http_build_query($params);

        return 'https://www.instagram.com/oauth/authorize?' . $query;
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
            throw SocialAccountException::authFailed('Instagram');
        }

        $tokenResponse = $this->exchangeInstagramCode($code);

        $accessToken = $tokenResponse['access_token'] ?? null;
        $instagramId = $tokenResponse['user_id'] ?? null;

        if (!$accessToken || !$instagramId) {
            throw SocialAccountException::authFailed('Instagram');
        }

        $profileResponse = Http::get("https://graph.instagram.com/v25.0/me", [
            'fields' => 'id,username',
            'access_token' => $accessToken,
        ])->json();

        $username = $profileResponse['username'] ?? 'Instagram User';

        $longLivedToken = $this->exchangeForLongLivedToken($accessToken);

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => 'instagram',
                'platform_id' => $instagramId,
            ],
            [
                'user_id' => $userId,
                'access_token' => $longLivedToken['token'],
                'refresh_token' => null,
                'expires_at' => $longLivedToken['expires_at'],
                'nickname' => $username,
                'avatar' => null,
            ]
        );
    }

    private function exchangeInstagramCode(string $code): array
    {
        return Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.meta.redirect'),
            'code' => $code,
        ])->json();
    }

    private function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => config('services.meta.app_secret'),
            'access_token' => $shortLivedToken,
        ])->json();

        if (isset($response['access_token'])) {
            return [
                'token' => $response['access_token'],
                'expires_at' => now()->addSeconds($response['expires_in'] ?? 5184000), // ~60 days
            ];
        }

        return [
            'token' => $shortLivedToken,
            'expires_at' => now()->addHour(),
        ];
    }
}

