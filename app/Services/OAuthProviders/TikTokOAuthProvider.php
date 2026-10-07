<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Enums\Platform;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OAuthProviders\Concerns\ResolvesAccountOwner;
use Illuminate\Support\Facades\Http;

class TikTokOAuthProvider implements OAuthProviderInterface
{
    use ResolvesAccountOwner;

    public const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

    public const REVOKE_URL = 'https://open.tiktokapis.com/v2/oauth/revoke/';

    private const AUTHORIZE_URL = 'https://www.tiktok.com/v2/auth/authorize/';

    private const USER_INFO_URL = 'https://open.tiktokapis.com/v2/user/info/';

    // video.publish é o escopo do Direct Post; video.upload (rascunho na inbox) não é usado.
    private const PUBLISH_SCOPE = 'video.publish';

    private const SCOPES = ['user.info.basic', self::PUBLISH_SCOPE];

    private const DEFAULT_TOKEN_TTL_SECONDS = 86400;

    // Monta a URL de login do TikTok; o state leva o workspace para o callback saber onde salvar a conta.
    public function getRedirectUrl(?User $user = null): string
    {
        $workspace = $user?->currentAccess?->workspace;

        if (! $workspace) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        $params = [
            'client_key' => config('services.tiktok.client_key'),
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
            'redirect_uri' => config('services.tiktok.redirect'),
            'state' => encrypt($workspace->uuid),
        ];

        return self::AUTHORIZE_URL.'?'.http_build_query($params);
    }

    // Callback do OAuth: troca o código pelo token e cria ou atualiza a conta conectada no workspace.
    public function syncAccount(User|Workspace $context, ?string $code = null): SocialAccount
    {
        [$workspaceId, $userId] = $this->accountOwner($context);

        if (! $code) {
            throw SocialAccountException::authFailed(Platform::TIKTOK->label());
        }

        $token = $this->requestToken($code);
        $profile = $this->fetchProfile($token['access_token']);

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => Platform::TIKTOK->value,
                'platform_id' => $token['open_id'],
            ],
            [
                'user_id' => $userId,
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($token['expires_in'] ?? self::DEFAULT_TOKEN_TTL_SECONDS),
                'nickname' => $profile['display_name'] ?? 'TikTok User',
                'avatar' => $profile['avatar_url'] ?? null,
            ]
        );
    }

    // Troca o código do callback pelo token e exige o escopo de publicar, que o usuário pode desmarcar.
    private function requestToken(string $code): array
    {
        $token = Http::asForm()->post(self::TOKEN_URL, [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.tiktok.redirect'),
        ])->json() ?? [];

        if (empty($token['access_token']) || empty($token['open_id'])) {
            throw SocialAccountException::authFailed(Platform::TIKTOK->label());
        }

        $grantedScopes = explode(',', $token['scope'] ?? '');

        if (! in_array(self::PUBLISH_SCOPE, $grantedScopes, true)) {
            throw SocialAccountException::missingPermissions(Platform::TIKTOK->label());
        }

        return $token;
    }

    // Pega nome e avatar da conta, que o token não traz, para exibir no sistema.
    private function fetchProfile(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get(self::USER_INFO_URL, ['fields' => 'open_id,avatar_url,display_name'])
            ->json('data.user') ?? [];
    }
}
