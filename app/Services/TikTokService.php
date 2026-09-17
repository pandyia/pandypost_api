<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Storage\StorageService;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokService implements SocialMediaServiceInterface
{
    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload para TikTok. Post ID: {$post->id}");
        $post->update(['status' => 'processing']);

        try {
            $accessToken = $account->getValidToken();
            $videoSize = $this->storageService->size($post->media_path);

            if ($videoSize <= 64 * 1024 * 1024) {
                $chunkSize = $videoSize;
                $totalChunkCount = 1;
            } else {
                $chunkSize = 10 * 1024 * 1024;
                $totalChunkCount = (int) ceil($videoSize / $chunkSize);
            }

            $payload = $post->payload ?? [];
            $caption = $post->caption ?: ($post->title ?: '');

            $postInfo = [
                'title' => $caption,
                'privacy_level' => Arr::get($payload, 'tiktok_privacy_level', 'PUBLIC_TO_EVERYONE'),
                'disable_comment' => (bool) Arr::get($payload, 'tiktok_disable_comment', false),
                'disable_duet' => (bool) Arr::get($payload, 'tiktok_disable_duet', false),
                'disable_stitch' => (bool) Arr::get($payload, 'tiktok_disable_stitch', false),
            ];

            if (Arr::has($payload, 'tiktok_brand_content_toggle')) {
                $postInfo['brand_content_toggle'] = (bool) Arr::get($payload, 'tiktok_brand_content_toggle');
            }

            $response = Http::withToken($accessToken)
                ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                    'post_info' => $postInfo,
                    'source_info' => [
                        'source' => 'FILE_UPLOAD',
                        'video_size' => $videoSize,
                        'chunk_size' => $chunkSize,
                        'total_chunk_count' => $totalChunkCount,
                    ],
                ])->json();

            $publishId = $response['data']['publish_id'] ?? null;
            $uploadUrl = $response['data']['upload_url'] ?? null;
            $errorCode = $response['error']['code'] ?? null;

            if ($errorCode === 'unaudited_client_can_only_post_to_private_accounts') {
                $errorMsg = 'App do TikTok não auditado: submeta o app para análise no TikTok Developer Portal ou cadastre a conta TikTok como Test Account no painel de desenvolvedores.';
                Log::error("Falha ao inicializar post no TikTok (App não auditado). Post ID: {$post->id}", ['response' => $response]);

                $post->update([
                    'status' => 'failed',
                    'error_message' => substr($errorMsg, 0, 255),
                ]);

                throw new Exception("Erro TikTok API: {$errorMsg}");
            }

            if (!$publishId || !$uploadUrl || ($errorCode && $errorCode !== 'ok')) {
                $errorMsg = $response['error']['message'] ?? 'Erro desconhecido ao inicializar publicação no TikTok';
                Log::error("Falha ao inicializar post no TikTok. Post ID: {$post->id}", ['response' => $response]);
                
                $post->update([
                    'status' => 'failed',
                    'error_message' => substr($errorMsg, 0, 255),
                ]);

                throw new Exception("Erro TikTok API: {$errorMsg}");
            }

            $stream = $this->storageService->readStream($post->media_path);
            try {
                for ($i = 0; $i < $totalChunkCount; $i++) {
                    $start = $i * $chunkSize;
                    $end = min(($i + 1) * $chunkSize - 1, $videoSize - 1);
                    $chunkLen = $end - $start + 1;

                    $chunkData = fread($stream, $chunkLen);

                    $uploadResponse = Http::withHeaders([
                        'Content-Type' => 'video/mp4',
                        'Content-Length' => (string) $chunkLen,
                        'Content-Range' => "bytes {$start}-{$end}/{$videoSize}",
                    ])->withBody($chunkData, 'video/mp4')
                      ->put($uploadUrl);

                    if (!$uploadResponse->successful()) {
                        $err = "HTTP {$uploadResponse->status()} - " . substr($uploadResponse->body(), 0, 200);
                        Log::error("Falha no upload de chunk do TikTok. Post ID: {$post->id}, Chunk: {$i}", ['error' => $err]);
                        $post->update([
                            'status' => 'failed',
                            'error_message' => "Erro no upload do vídeo para o TikTok: {$err}",
                        ]);
                        throw new Exception("Erro TikTok Binary Upload: {$err}");
                    }
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $post->update([
                'container_id' => $publishId,
                'container_created_at' => now(),
            ]);

            Log::info("Post no TikTok enviado com sucesso. Publish ID: {$publishId}. Disparando job de monitoramento.");

            CheckTikTokPostStatusJob::dispatch($post, $account, $publishId);

        } catch (Exception $e) {
            Log::error("Erro no upload do TikTok para o Post {$post->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
