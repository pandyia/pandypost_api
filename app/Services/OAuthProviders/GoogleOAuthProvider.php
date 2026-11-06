<?php

namespace App\Services\OAuthProviders;

use App\Enums\Platform;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthProvider extends AbstractOAuthProvider
{
    public function getRedirectUrl(?User $user = null): string
    {
        $state = $this->stateFor($user, 'google');

        return Socialite::driver('google')->stateless()->scopes([
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/yt-analytics.readonly',
        ])->with([
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ])->redirect()->getTargetUrl();
    }

    public function syncAccount(User $user, ?string $code = null, ?Workspace $workspace = null): SocialAccount
    {
        [$workspaceId, $userId] = $this->ownerIds($user, $workspace);

        try {
            $socialiteUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            throw SocialAccountException::oauthTokenExchangeFailed();
        }

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => Platform::YOUTUBE->value,
                'platform_id' => $socialiteUser->getId(),
            ],
            [
                'user_id' => $userId,
                'access_token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken ?? null,
                'expires_at' => now()->addSeconds($socialiteUser->expiresIn ?? 3600),
                'nickname' => $socialiteUser->getName(),
                'avatar' => $socialiteUser->getAvatar(),
            ]
        );
    }
}
