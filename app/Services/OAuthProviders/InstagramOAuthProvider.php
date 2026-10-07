<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Enums\Platform;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\InstagramService;
use App\Services\OAuthProviders\Concerns\ResolvesAccountOwner;
use Illuminate\Support\Facades\Http;

class InstagramOAuthProvider implements OAuthProviderInterface
{
    use ResolvesAccountOwner;

    public const REFRESH_URL = 'https://graph.instagram.com/refresh_access_token';

    private const AUTHORIZE_URL = 'https://www.instagram.com/oauth/authorize';

    private const SHORT_LIVED_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

    private const LONG_LIVED_TOKEN_URL = 'https://graph.instagram.com/access_token';

    // Só publicação: o sistema não lê mensagens (DMs).
    private const SCOPES = ['instagram_business_basic', 'instagram_business_content_publish'];

    private const LONG_LIVED_TOKEN_TTL_SECONDS = 5184000; // 60 dias

    // Monta a URL de login do Instagram; o state leva o workspace para o callback saber onde salvar a conta.
    public function getRedirectUrl(?User $user = null): string
    {
        $workspace = $user?->currentAccess?->workspace;

        if (! $workspace) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => config('services.meta.redirect'),
            'response_type' => 'code',
            'scope' => implode(',', self::SCOPES),
            'state' => encrypt($workspace->uuid),
        ]);
    }

    // Callback do OAuth: troca o código pelo token e cria ou atualiza a conta conectada no workspace.
    public function syncAccount(User|Workspace $context, ?string $code = null): SocialAccount
    {
        [$workspaceId, $userId] = $this->accountOwner($context);

        if (! $code) {
            throw SocialAccountException::authFailed(Platform::INSTAGRAM->label());
        }

        $shortLivedToken = $this->requestShortLivedToken($code);
        $profile = $this->fetchProfile($shortLivedToken['access_token']);
        $longLivedToken = $this->exchangeForLongLivedToken($shortLivedToken['access_token']);

        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => Platform::INSTAGRAM->value,
                'platform_id' => (string) $shortLivedToken['user_id'],
            ],
            [
                'user_id' => $userId,
                'access_token' => $longLivedToken['access_token'],
                'refresh_token' => null,
                'expires_at' => now()->addSeconds($longLivedToken['expires_in'] ?? self::LONG_LIVED_TOKEN_TTL_SECONDS),
                'nickname' => $profile['username'] ?? 'Instagram User',
                'avatar' => null,
            ]
        );
    }

    // Troca o código do callback por um token de 1 hora e o id da conta.
    private function requestShortLivedToken(string $code): array
    {
        $token = Http::asForm()->post(self::SHORT_LIVED_TOKEN_URL, [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.meta.redirect'),
            'code' => $code,
        ])->json() ?? [];

        if (empty($token['access_token']) || empty($token['user_id'])) {
            throw SocialAccountException::authFailed(Platform::INSTAGRAM->label());
        }

        return $token;
    }

    // Pega o @username da conta, que o token não traz, para usar como apelido.
    private function fetchProfile(string $accessToken): array
    {
        return Http::get(InstagramService::GRAPH_API_URL.'/me', [
            'fields' => 'id,username',
            'access_token' => $accessToken,
        ])->json() ?? [];
    }

    // Troca o token de 1 hora por um de 60 dias, senão a conta cairia logo após conectar.
    private function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $token = Http::get(self::LONG_LIVED_TOKEN_URL, [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => config('services.meta.app_secret'),
            'access_token' => $shortLivedToken,
        ])->json() ?? [];

        if (empty($token['access_token'])) {
            throw SocialAccountException::tokenExchangeFailed();
        }

        return $token;
    }
}
