<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthProvider implements OAuthProviderInterface
{
    private const PLATFORM_NAME = 'youtube';

    public function getRedirectUrl(?User $user = null): string
    {
        $workspace = $user?->currentAccess?->workspace;

        if (!$workspace) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        $state = encrypt($workspace->uuid);

        return Socialite::driver('google')->stateless()->scopes([
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly'
        ])->with([
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ])->redirect()->getTargetUrl();
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

        try {
            $socialiteUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            throw SocialAccountException::oauthTokenExchangeFailed();
        }

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => self::PLATFORM_NAME,
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
