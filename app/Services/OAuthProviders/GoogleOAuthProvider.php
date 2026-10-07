<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Enums\Platform;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OAuthProviders\Concerns\ResolvesAccountOwner;
use Exception;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class GoogleOAuthProvider implements OAuthProviderInterface
{
    use ResolvesAccountOwner;

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const SCOPES = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly',
        'https://www.googleapis.com/auth/yt-analytics.readonly',
    ];

    private const DEFAULT_TOKEN_TTL_SECONDS = 3600;

    // Monta a URL de login do Google; o state leva o workspace para o callback saber onde salvar a conta.
    public function getRedirectUrl(?User $user = null): string
    {
        $workspace = $user?->currentAccess?->workspace;

        if (! $workspace) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        return $this->driver()
            ->scopes(self::SCOPES)
            ->with([
                'state' => encrypt($workspace->uuid),
                // offline + consent: só assim o Google devolve o refresh token.
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect()
            ->getTargetUrl();
    }

    // Callback do OAuth: o Socialite lê o código da requisição e cria ou atualiza a conta conectada no workspace.
    public function syncAccount(User|Workspace $context, ?string $code = null): SocialAccount
    {
        [$workspaceId, $userId] = $this->accountOwner($context);

        try {
            $googleUser = $this->driver()->user();
        } catch (Exception) {
            throw SocialAccountException::oauthTokenExchangeFailed();
        }

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => Platform::YOUTUBE->value,
                'platform_id' => $googleUser->getId(),
            ],
            [
                'user_id' => $userId,
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken,
                'expires_at' => now()->addSeconds($googleUser->expiresIn ?? self::DEFAULT_TOKEN_TTL_SECONDS),
                'nickname' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
            ]
        );
    }

    // Stateless porque a API não tem sessão; o state é o uuid do workspace criptografado.
    private function driver(): AbstractProvider
    {
        return Socialite::driver('google')->stateless();
    }
}
