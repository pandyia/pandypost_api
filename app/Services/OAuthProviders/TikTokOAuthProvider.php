<?php

namespace App\Services\OAuthProviders;

use App\Enums\Platform;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Login Kit (web) + Display API.
 * Escopos de leitura para o analytics (spec 003) e de publicação (spec 011).
 */
class TikTokOAuthProvider extends AbstractOAuthProvider
{
    private const AUTHORIZE_URL = 'https://www.tiktok.com/v2/auth/authorize/';

    private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

    private const USER_INFO_URL = 'https://open.tiktokapis.com/v2/user/info/';

    // video.upload/video.publish = Direct Post (spec 011). Contas conectadas antes precisam reconectar.
    private const SCOPES = 'user.info.basic,user.info.profile,user.info.stats,video.list,video.upload,video.publish';

    public function getRedirectUrl(?User $user = null): string
    {
        if (! config('services.tiktok.client_key')) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_key' => config('services.tiktok.client_key'),
            'scope' => self::SCOPES,
            'response_type' => 'code',
            'redirect_uri' => config('services.tiktok.redirect'),
            'state' => $this->stateFor($user, 'tiktok'),
        ]);
    }

    public function syncAccount(User $user, ?string $code = null, ?Workspace $workspace = null): SocialAccount
    {
        [$workspaceId, $userId] = $this->ownerIds($user, $workspace);

        if (! $code) {
            throw SocialAccountException::authFailed('TikTok');
        }

        $token = $this->exchangeCode($code);

        if (empty($token['access_token']) || empty($token['open_id'])) {
            throw SocialAccountException::oauthTokenExchangeFailed();
        }

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
                'expires_at' => now()->addSeconds($token['expires_in'] ?? 86400),
                'nickname' => $profile['display_name'] ?? 'TikTok',
                'avatar' => $profile['avatar_url'] ?? null,
                'username' => $profile['username'] ?? null,
                // Escopos realmente concedidos (o usuário pode desmarcar); decidem o can_publish.
                'scopes' => $token['scope'] ?? null,
            ]
        );
    }

    /** Perfil é complemento: se falhar, a conta é criada mesmo assim (com nickname padrão) e o motivo vai para o log. */
    private function fetchProfile(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get(self::USER_INFO_URL, ['fields' => 'open_id,display_name,avatar_url,username']);
        } catch (ConnectionException) {
            Log::warning('TikTok user/info indisponível (falha de conexão); conta criada sem perfil.');

            return [];
        }

        $code = $response->json('error.code');
        if ($response->failed() || ($code !== null && $code !== 'ok')) {
            Log::warning('TikTok user/info falhou (HTTP '.$response->status().', código '.($code ?? 'ausente').'); conta criada sem perfil.');

            return [];
        }

        return $response->json('data.user', []);
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
