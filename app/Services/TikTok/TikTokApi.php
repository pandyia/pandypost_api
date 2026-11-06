<?php

namespace App\Services\TikTok;

use App\Contracts\PlatformTokenApiInterface;
use App\Exceptions\SocialAccountException;
use App\Exceptions\TikTokException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Content Posting API do TikTok (Direct Post). Concentra URLs, headers e a conversão de
 * resposta de erro em TikTokException: nada de body cru ou token sai daqui.
 */
class TikTokApi implements PlatformTokenApiInterface
{
    private const BASE_URL = 'https://open.tiktokapis.com/v2/post/publish';

    private const OAUTH_BASE_URL = 'https://open.tiktokapis.com/v2/oauth';

    // Domínios (e subdomínios) onde o TikTok hospeda o upload; o upload_url vem da resposta, então não é confiável.
    private const UPLOAD_HOST_SUFFIXES = ['tiktokapis.com', 'tiktok.com'];

    // TikTok responde 206 a chunk parcial aceito e 201 ao último.
    private const CHUNK_OK_STATUSES = [200, 201, 206];

    /** @return array<string, mixed> data de creator_info/query */
    public function creatorInfo(string $token): array
    {
        // Sem corpo, mas o TikTok exige o Content-Type JSON; sem ele responde invalid_params.
        return $this->data(fn () => $this->api($token)
            ->withBody('', 'application/json; charset=UTF-8')
            ->post(self::BASE_URL.'/creator_info/query/'));
    }

    /**
     * @param  array<string, mixed>  $postInfo
     * @return array{publish_id: string, upload_url: string}
     */
    public function initUpload(string $token, array $postInfo, int $size, int $chunkSize, int $totalChunks): array
    {
        $data = $this->data(fn () => $this->api($token)->post(self::BASE_URL.'/video/init/', [
            'post_info' => $postInfo,
            'source_info' => [
                'source' => 'FILE_UPLOAD',
                'video_size' => $size,
                'chunk_size' => $chunkSize,
                'total_chunk_count' => $totalChunks,
            ],
        ]));

        if (empty($data['publish_id']) || empty($data['upload_url'])) {
            throw TikTokException::invalidResponse();
        }

        $this->assertTrustedUploadUrl((string) $data['upload_url']);

        return ['publish_id' => (string) $data['publish_id'], 'upload_url' => (string) $data['upload_url']];
    }

    public function uploadChunk(string $uploadUrl, string $body, int $start, int $end, int $total, string $mime): void
    {
        $this->assertTrustedUploadUrl($uploadUrl);

        try {
            // Sem seguir redirecionamento: a URL assinada só vale para o host validado, nunca para outro destino.
            $response = Http::timeout(120)
                ->withoutRedirecting()
                ->withHeaders(['Content-Range' => "bytes {$start}-{$end}/{$total}"])
                ->withBody($body, $mime)
                ->put($uploadUrl);
        } catch (ConnectionException|RequestException $e) {
            // A mensagem da exceção traz a URL assinada do upload: só a classe vai para o log.
            Log::warning('Falha de conexão ao enviar chunk ao TikTok ('.$e::class.').');

            throw TikTokException::uploadFailed();
        }

        if (! in_array($response->status(), self::CHUNK_OK_STATUSES, true)) {
            Log::warning("TikTok recusou o chunk {$start}-{$end} (HTTP {$response->status()}).");

            throw TikTokException::uploadFailed();
        }
    }

    /** @return array<string, mixed> data de status/fetch */
    public function fetchStatus(string $token, string $publishId): array
    {
        return $this->data(fn () => $this->api($token)->post(self::BASE_URL.'/status/fetch/', ['publish_id' => $publishId]));
    }

    /**
     * O refresh token pode ser trocado a cada renovação; sem um novo, vale o atual.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function refreshAccountToken(SocialAccount $account): array
    {
        if (! $account->refresh_token) {
            throw SocialAccountException::refreshTokenMissing();
        }

        $response = Http::asForm()->post(self::OAUTH_BASE_URL.'/token/', [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw SocialAccountException::tokenRefreshFailed('TikTok');
        }

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?? $account->refresh_token,
            'expires_in' => $response->json('expires_in') ?? 86400,
        ];
    }

    public function revokeAccountToken(SocialAccount $account): void
    {
        Http::asForm()->post(self::OAUTH_BASE_URL.'/revoke/', [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'token' => $account->access_token,
        ]);
    }

    private function api(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    /** O upload_url só é aceito em https e em host do TikTok; qualquer outro destino receberia o vídeo (SSRF). */
    private function assertTrustedUploadUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $trustedHost = collect(self::UPLOAD_HOST_SUFFIXES)
            ->contains(fn (string $suffix) => $host === $suffix || str_ends_with($host, '.'.$suffix));

        if (($parts['scheme'] ?? null) !== 'https' || ! $trustedHost || isset($parts['user']) || isset($parts['pass'])) {
            Log::warning('TikTok devolveu upload_url fora do esperado (host '.($host ?: 'ausente').'); envio bloqueado.');

            throw TikTokException::invalidResponse();
        }
    }

    /**
     * Executa a chamada e converte falha de conexão em TikTokException (a mensagem da exceção de
     * rede traz a URL da requisição e nunca deve sair daqui).
     *
     * @param  callable(): Response  $request
     * @return array<string, mixed>
     */
    private function data(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Falha de conexão com a API do TikTok ('.$e::class.').');

            throw TikTokException::apiUnavailable();
        }

        $code = $response->json('error.code');

        if ($response->failed() || ($code !== null && $code !== 'ok')) {
            // Só o código vai para o log: message/log_id do TikTok são texto de terceiro.
            Log::warning('TikTok devolveu erro (HTTP '.$response->status().', código '.($code ?? 'ausente').').');

            throw TikTokException::fromApiCode($code);
        }

        return $response->json('data') ?? [];
    }
}
