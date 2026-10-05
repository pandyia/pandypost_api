<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Enums\Platform;
use App\Enums\TikTokPrivacyLevel;
use App\Exceptions\ScheduledPostException;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokService implements SocialMediaServiceInterface
{
    private const INIT_URL = 'https://open.tiktokapis.com/v2/post/publish/video/init/';

    // O TikTok aceita pedaços de 5 a 64 MB; o último pode chegar a 128 MB.
    private const CHUNK_SIZE = 10 * 1024 * 1024;

    private const CHUNK_UPLOAD_TIMEOUT_SECONDS = 120;

    private const DEFAULT_MIME_TYPE = 'video/mp4';

    private const MIME_TYPES = [
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
    ];

    // Códigos de erro documentados do endpoint de init → motivo exibido ao usuário.
    private const ERROR_MESSAGES = [
        'unaudited_client_can_only_post_to_private_accounts' => 'app ainda não auditado pelo TikTok. Até a aprovação, só é possível publicar em contas privadas.',
        'privacy_level_option_mismatch' => 'a privacidade escolhida não está disponível para esta conta.',
        'spam_risk_too_many_posts' => 'a conta atingiu o limite diário de publicações. Tente novamente mais tarde.',
        'spam_risk_user_banned_from_posting' => 'a conta está impedida de publicar pelo TikTok.',
        'reached_active_user_cap' => 'o app atingiu o limite diário de criadores do TikTok. Tente novamente mais tarde.',
        'access_token_invalid' => 'o acesso à conta expirou ou foi revogado. Reconecte a conta.',
        'scope_not_authorized' => 'a conta não autorizou a publicação de vídeos. Reconecte a conta e autorize todas as permissões.',
        'rate_limit_exceeded' => 'limite de requisições do TikTok atingido. Tente novamente em instantes.',
    ];

    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload para TikTok. Post ID: {$post->id}");
        $post->update(['status' => 'processing']);

        $accessToken = $account->getValidToken();
        $sourceInfo = $this->buildSourceInfo($this->storageService->size($post->media_path));

        $upload = $this->initializeUpload($accessToken, $post, $sourceInfo);
        $this->uploadChunks($upload['upload_url'], $post, $sourceInfo);

        $post->update([
            'container_id' => $upload['publish_id'],
            'container_created_at' => now(),
        ]);

        Log::info("Vídeo do post {$post->id} enviado ao TikTok (publish_id: {$upload['publish_id']}). Acompanhando o processamento.");
        CheckTikTokPostStatusJob::dispatch($post, $account, $upload['publish_id']);
    }

    /**
     * Vídeo menor que um pedaço vai inteiro. Acima disso, o total de pedaços é arredondado
     * para baixo e o último absorve o resto (regra do TikTok).
     */
    private function buildSourceInfo(int $videoSize): array
    {
        if ($videoSize === 0) {
            throw ScheduledPostException::publishFailed(Platform::TIKTOK->label(), 'o arquivo de vídeo está vazio.');
        }

        $chunkSize = min($videoSize, self::CHUNK_SIZE);

        return [
            'source' => 'FILE_UPLOAD',
            'video_size' => $videoSize,
            'chunk_size' => $chunkSize,
            'total_chunk_count' => intdiv($videoSize, $chunkSize),
        ];
    }

    /**
     * Cria a publicação no TikTok e devolve o publish_id e a URL de upload.
     */
    private function initializeUpload(string $accessToken, ScheduledPost $post, array $sourceInfo): array
    {
        $response = Http::withToken($accessToken)->post(self::INIT_URL, [
            'post_info' => $this->buildPostInfo($post),
            'source_info' => $sourceInfo,
        ]);

        $errorCode = $response->json('error.code');
        $data = $response->json('data') ?? [];

        if (($errorCode && $errorCode !== 'ok') || empty($data['publish_id']) || empty($data['upload_url'])) {
            Log::error("TikTok recusou o início da publicação do post {$post->id}.", ['response' => $response->json()]);

            throw ScheduledPostException::publishFailed(
                Platform::TIKTOK->label(),
                $this->describeError($errorCode, $response->json('error.message')),
            );
        }

        return $data;
    }

    private function buildPostInfo(ScheduledPost $post): array
    {
        $payload = $post->payload ?? [];

        $postInfo = [
            'title' => $post->caption ?: ($post->title ?? ''),
            'privacy_level' => Arr::get($payload, 'tiktok_privacy_level', TikTokPrivacyLevel::PUBLIC_TO_EVERYONE->value),
            'disable_comment' => (bool) Arr::get($payload, 'tiktok_disable_comment', false),
            'disable_duet' => (bool) Arr::get($payload, 'tiktok_disable_duet', false),
            'disable_stitch' => (bool) Arr::get($payload, 'tiktok_disable_stitch', false),
        ];

        if (Arr::has($payload, 'tiktok_brand_content_toggle')) {
            $postInfo['brand_content_toggle'] = (bool) $payload['tiktok_brand_content_toggle'];
        }

        return $postInfo;
    }

    /**
     * Envia o vídeo do S3 para o TikTok em pedaços, sem materializar o arquivo inteiro na memória.
     */
    private function uploadChunks(string $uploadUrl, ScheduledPost $post, array $sourceInfo): void
    {
        $videoSize = $sourceInfo['video_size'];
        $lastIndex = $sourceInfo['total_chunk_count'] - 1;
        $mimeType = $this->mimeType($post->media_path);
        $stream = $this->storageService->readStream($post->media_path);

        try {
            for ($index = 0; $index <= $lastIndex; $index++) {
                $start = $index * $sourceInfo['chunk_size'];
                $end = $index === $lastIndex ? $videoSize - 1 : $start + $sourceInfo['chunk_size'] - 1;
                $length = $end - $start + 1;

                // stream_get_contents lê até completar o tamanho pedido; fread pararia no primeiro pacote do stream do S3.
                $chunk = stream_get_contents($stream, $length);

                if ($chunk === false || strlen($chunk) !== $length) {
                    throw ScheduledPostException::publishFailed(Platform::TIKTOK->label(), 'não foi possível ler o arquivo de vídeo do storage.');
                }

                $response = Http::timeout(self::CHUNK_UPLOAD_TIMEOUT_SECONDS)
                    ->withHeaders(['Content-Range' => "bytes {$start}-{$end}/{$videoSize}"])
                    ->withBody($chunk, $mimeType)
                    ->put($uploadUrl);

                if ($response->failed()) {
                    Log::error("TikTok recusou o pedaço {$index} do vídeo do post {$post->id}.", [
                        'status' => $response->status(),
                        'body' => Str::limit($response->body(), 500),
                    ]);

                    throw ScheduledPostException::publishFailed(
                        Platform::TIKTOK->label(),
                        "o TikTok recusou o envio do vídeo (HTTP {$response->status()}).",
                    );
                }
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function mimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::MIME_TYPES[$extension] ?? self::DEFAULT_MIME_TYPE;
    }

    private function describeError(?string $code, ?string $message): string
    {
        if ($code !== null && isset(self::ERROR_MESSAGES[$code])) {
            return self::ERROR_MESSAGES[$code];
        }

        $reason = Str::limit($message ?: 'resposta inesperada ao iniciar a publicação', 150);

        return $code ? "{$reason} ({$code})" : $reason;
    }
}
