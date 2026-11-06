<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Publica direto no TikTok (Direct Post, scope video.publish).
 * Enquanto o app não for auditado pelo TikTok, só é aceito privacy_level SELF_ONLY
 * (config services.tiktok.default_privacy / TIKTOK_DEFAULT_PRIVACY).
 */
class TikTokService implements SocialMediaServiceInterface
{
    private const INIT_URL = 'https://open.tiktokapis.com/v2/post/publish/video/init/';
    private const STATUS_URL = 'https://open.tiktokapis.com/v2/post/publish/status/fetch/';

    // TikTok: cada chunk entre 5MB e 64MB (o último pode chegar a 128MB). Vídeos < 5MB vão em 1 chunk.
    private const CHUNK_SIZE = 10 * 1024 * 1024;
    private const STATUS_ATTEMPTS = 20;
    private const STATUS_DELAY_SECONDS = 3;

    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload para TikTok. Post ID: {$post->id}");
        $post->update(['status' => 'processing']);

        try {
            $token = $account->getValidToken();
            $size = $this->storageService->size($post->media_path);
            $mime = $this->storageService->mimeType($post->media_path);
            $mime = str_starts_with($mime, 'video/') ? $mime : 'video/mp4';

            $chunkSize = $size < self::CHUNK_SIZE ? $size : self::CHUNK_SIZE;
            $totalChunks = max(1, intdiv($size, $chunkSize));

            $init = Http::withToken($token)
                ->acceptJson()
                ->post(self::INIT_URL, [
                    'post_info' => $this->buildPostInfo($post),
                    'source_info' => [
                        'source' => 'FILE_UPLOAD',
                        'video_size' => $size,
                        'chunk_size' => $chunkSize,
                        'total_chunk_count' => $totalChunks,
                    ],
                ])->json();

            $publishId = $init['data']['publish_id'] ?? null;
            $uploadUrl = $init['data']['upload_url'] ?? null;

            if (!$publishId || !$uploadUrl) {
                throw new Exception('Falha ao inicializar upload no TikTok: ' . json_encode($init));
            }

            $this->streamChunks($uploadUrl, $post->media_path, $size, $chunkSize, $totalChunks, $mime);
            $videoId = $this->waitForPublish($token, $publishId);

            $post->update([
                'status' => 'published',
                // ID real do vídeo (só vem quando o post é público); senão fica null e o link não é gerado.
                'platform_post_id' => $videoId,
                'published_at' => now(),
            ]);

            Log::info("Post {$post->id} publicado no TikTok (publish_id {$publishId}).");
            $this->cleanupFiles($post);
        } catch (Exception $e) {
            Log::error("Erro no upload do TikTok para o Post {$post->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function buildPostInfo(ScheduledPost $post): array
    {
        $payload = $post->payload ?? [];
        $title = trim((string) ($post->caption ?: $post->title));

        return [
            'title' => mb_substr($title, 0, 2200),
            'privacy_level' => $payload['tiktok_privacy_level'] ?? config('services.tiktok.default_privacy', 'SELF_ONLY'),
            'disable_comment' => (bool) ($payload['tiktok_disable_comment'] ?? false),
            'disable_duet' => (bool) ($payload['tiktok_disable_duet'] ?? false),
            'disable_stitch' => (bool) ($payload['tiktok_disable_stitch'] ?? false),
        ];
    }

    private function streamChunks(string $uploadUrl, string $path, int $size, int $chunkSize, int $totalChunks, string $mime): void
    {
        $handle = $this->storageService->readStream($path);

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $start = $i * $chunkSize;
                // O último chunk absorve o resto da divisão.
                $end = ($i === $totalChunks - 1) ? $size - 1 : $start + $chunkSize - 1;
                $length = $end - $start + 1;

                $body = $this->readExactly($handle, $length);

                $response = Http::withHeaders([
                    'Content-Type' => $mime,
                    'Content-Range' => "bytes {$start}-{$end}/{$size}",
                ])->withBody($body, $mime)->put($uploadUrl);

                // 206 = chunk parcial aceito, 201 = upload completo.
                if (!in_array($response->status(), [200, 201, 206], true)) {
                    throw new Exception("Falha no envio do chunk {$i} ao TikTok ({$response->status()}): " . $response->body());
                }
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /** fread em streams S3 pode devolver menos bytes que o pedido; garante o tamanho exato. */
    private function readExactly($handle, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length && !feof($handle)) {
            $data = fread($handle, $length - strlen($buffer));
            if ($data === false) {
                break;
            }
            $buffer .= $data;
        }

        if (strlen($buffer) !== $length) {
            throw new Exception("Leitura incompleta do arquivo no S3 (esperado {$length}, lido " . strlen($buffer) . ').');
        }

        return $buffer;
    }

    private function waitForPublish(string $token, string $publishId): ?string
    {
        for ($attempt = 0; $attempt < self::STATUS_ATTEMPTS; $attempt++) {
            $data = Http::withToken($token)
                ->acceptJson()
                ->post(self::STATUS_URL, ['publish_id' => $publishId])
                ->json('data', []);

            $status = $data['status'] ?? null;

            if (in_array($status, ['PUBLISH_COMPLETE'], true)) {
                // (sic) o TikTok escreve "publicaly" com um L só.
                $id = $data['publicaly_available_post_id'][0] ?? null;

                return $id ? (string) $id : null;
            }

            if ($status === 'FAILED') {
                throw new Exception('TikTok recusou o vídeo: ' . ($data['fail_reason'] ?? 'motivo desconhecido'));
            }

            sleep(self::STATUS_DELAY_SECONDS);
        }

        // Upload foi aceito; o processamento pode só estar demorando. Não falha o post.
        Log::warning("TikTok publish_id {$publishId}: status não confirmado após polling.");

        return null;
    }

    private function cleanupFiles(ScheduledPost $post): void
    {
        $paths = [$post->media_path];

        $thumbnailPath = ($post->payload ?? [])['thumbnail_path'] ?? null;
        if ($thumbnailPath) {
            $paths[] = $thumbnailPath;
        }

        $this->storageService->deleteIfUnused($paths, $post->id);
    }
}
