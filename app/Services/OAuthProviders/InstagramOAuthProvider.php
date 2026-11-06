<?php

namespace App\Services\OAuthProviders;

use App\Enums\Platform;
use App\Exceptions\InstagramException;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Instagram\InstagramApi;

class InstagramOAuthProvider extends AbstractOAuthProvider
{
    private const DEFAULT_USERNAME = 'Instagram User';

    public function __construct(private readonly InstagramApi $api) {}

    public function getRedirectUrl(?User $user = null): string
    {
        $state = $this->stateFor($user, 'instagram');

        $params = [
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => config('services.meta.redirect'),
            'response_type' => 'code',
            // manage_insights: leitura das métricas do dashboard (specs/003 RF-13).
            'scope' => 'instagram_business_basic,instagram_business_content_publish,instagram_business_manage_messages,instagram_business_manage_insights',
            'state' => $state,
        ];

        $query = http_build_query($params);

        return 'https://www.instagram.com/oauth/authorize?'.$query;
    }

    public function syncAccount(User $user, ?string $code = null, ?Workspace $workspace = null): SocialAccount
    {
        [$workspaceId, $userId] = $this->ownerIds($user, $workspace);

        if (! $code) {
            throw SocialAccountException::authFailed('Instagram');
        }

        try {
            $tokenResponse = $this->api->exchangeCode($code);
        } catch (InstagramException) {
            throw SocialAccountException::authFailed('Instagram');
        }

        $accessToken = $tokenResponse['access_token'] ?? null;
        $instagramId = $tokenResponse['user_id'] ?? null;

        if (! $accessToken || ! $instagramId) {
            throw SocialAccountException::authFailed('Instagram');
        }

        $username = $this->fetchUsername($accessToken);
        $longLivedToken = $this->exchangeForLongLivedToken($accessToken);

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => Platform::INSTAGRAM->value,
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

    private function fetchUsername(string $accessToken): string
    {
        try {
            return $this->api->profile($accessToken)['username'] ?? self::DEFAULT_USERNAME;
        } catch (InstagramException) {
            return self::DEFAULT_USERNAME;
        }
    }

    private function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        try {
            $response = $this->api->longLivedToken($shortLivedToken);
        } catch (InstagramException) {
            $response = [];
        }

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
