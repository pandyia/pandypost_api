<?php

namespace App\Services\Instagram;

use App\Contracts\PlatformTokenApiInterface;
use App\Exceptions\InstagramException;
use App\Exceptions\ScheduledPostException;
use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Graph API do Instagram (Instagram Login). Concentra URLs, versão e a conversão de erro em
 * InstagramException: body cru e token (que vai na query/corpo) não saem daqui.
 */
class InstagramApi implements PlatformTokenApiInterface
{
    public const BASE_URL = 'https://graph.instagram.com';

    public const GRAPH_URL = self::BASE_URL.'/v25.0';

    private const CODE_EXCHANGE_URL = 'https://api.instagram.com/oauth/access_token';

    public function containerStatus(string $token, string $containerId): ?string
    {
        $data = $this->data(fn () => Http::timeout(30)->get(self::GRAPH_URL."/{$containerId}", [
            'fields' => 'status_code',
            'access_token' => $token,
        ]));

        return $data['status_code'] ?? null;
    }

    /** @param  array<string, mixed>  $media  caption e campos de mídia (video_url/media_type ou image_url) */
    public function createContainer(string $token, string $accountId, array $media): string
    {
        $data = $this->data(fn () => Http::timeout(30)->post(self::GRAPH_URL."/{$accountId}/media", $media + [
            'access_token' => $token,
        ]));

        return $data['id'] ?? throw InstagramException::invalidResponse();
    }

    /** Devolve o id da mídia publicada (a resposta da Meta pode vir sem ele). */
    public function publish(string $token, string $accountId, string $containerId): ?string
    {
        $data = $this->data(fn () => Http::timeout(30)->post(self::GRAPH_URL."/{$accountId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $token,
        ]));

        return $data['id'] ?? null;
    }

    /**
     * O id do media_publish é interno; o link público (com o código do post) só vem do campo permalink.
     *
     * @throws ScheduledPostException
     */
    public function permalink(string $token, string $mediaId): string
    {
        try {
            $response = Http::timeout(30)->get(self::GRAPH_URL."/{$mediaId}", [
                'fields' => 'permalink',
                'access_token' => $token,
            ]);
        } catch (ConnectionException) {
            throw ScheduledPostException::permalinkUnavailable();
        }

        $permalink = $response->json('permalink');

        // Vai para platform_post_url e para o front: só string https do próprio Instagram (nunca javascript:, array etc.).
        if (! is_string($permalink) || ! $this->isInstagramUrl($permalink)) {
            throw ScheduledPostException::permalinkUnavailable();
        }

        return $permalink;
    }

    private function isInstagramUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && ($host === 'instagram.com' || str_ends_with($host, '.instagram.com'));
    }

    /** @return array<string, mixed> access_token e user_id do token de curta duração */
    public function exchangeCode(string $code): array
    {
        return $this->data(fn () => Http::timeout(30)->asForm()->post(self::CODE_EXCHANGE_URL, [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.meta.redirect'),
            'code' => $code,
        ]));
    }

    /** @return array<string, mixed> id e username */
    public function profile(string $token): array
    {
        return $this->data(fn () => Http::timeout(30)->get(self::GRAPH_URL.'/me', [
            'fields' => 'id,username',
            'access_token' => $token,
        ]));
    }

    /** @return array<string, mixed> access_token e expires_in do token de longa duração (~60 dias) */
    public function longLivedToken(string $shortLivedToken): array
    {
        return $this->data(fn () => Http::timeout(30)->get(self::BASE_URL.'/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => config('services.meta.app_secret'),
            'access_token' => $shortLivedToken,
        ]));
    }

    /** @return array{access_token: string, expires_in: int} */
    public function refreshToken(string $token): array
    {
        $data = $this->data(fn () => Http::timeout(30)->get(self::BASE_URL.'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ]));

        return [
            'access_token' => $data['access_token'] ?? throw InstagramException::invalidResponse(),
            'expires_in' => $data['expires_in'] ?? 5184000,
        ];
    }

    /** O token de longa duração é o próprio credencial de renovação (não há refresh_token). */
    public function refreshAccountToken(SocialAccount $account): array
    {
        try {
            return $this->refreshToken($account->access_token);
        } catch (InstagramException) {
            throw SocialAccountException::tokenRefreshFailed('Instagram');
        }
    }

    /** A Meta não tem endpoint de revogação: a desconexão é só o delete do registro. */
    public function revokeAccountToken(SocialAccount $account): void {}

    /**
     * Executa a chamada e converte falha em InstagramException. A mensagem da exceção de rede traz a
     * URL da requisição (com `access_token` nos GET) e o body da Meta é texto de terceiro: nenhum dos dois sai daqui.
     *
     * @param  callable(): Response  $request
     * @return array<string, mixed>
     */
    private function data(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com a API do Instagram ('.$e::class.').');

            throw InstagramException::connectionFailed();
        }

        if ($response->failed() || $response->json('error') !== null) {
            $code = $response->json('error.code');
            Log::warning('Instagram devolveu erro (HTTP '.$response->status().', código '.($code ?? 'ausente').').');

            throw InstagramException::fromApiCode(is_numeric($code) ? (int) $code : null);
        }

        return $response->json() ?? [];
    }
}
