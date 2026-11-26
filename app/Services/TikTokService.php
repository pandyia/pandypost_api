<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Enums\ScheduledPostStatus;
use App\Exceptions\TikTokException;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Payloads\Builders\TikTokPayloadBuilder;
use App\Services\Storage\StorageService;
use App\Services\TikTok\TikTokApi;
use Illuminate\Support\Facades\Log;

/**
 * Direct Post do TikTok (spec 011). O upload() só INICIA a publicação: valida, faz o init e envia
 * o arquivo. Quem confirma o resultado (published/failed) é o CheckTikTokPostStatusJob.
 * Erro propaga como TikTokException; o PublishPostJob::failed() é quem grava `failed`.
 */
class TikTokService implements SocialMediaServiceInterface
{
    // TikTok: cada chunk entre 5MB e 64MB (o último pode chegar a 128MB). Vídeo menor que 2 chunks vai inteiro.
    private const CHUNK_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private readonly StorageService $storageService,
        private readonly TikTokApi $api,
    ) {}

    /**
     * Opções do criador (privacidades permitidas, interações, duração máxima) para o front montar o post.
     *
     * @return array<string, mixed>
     */
    public function creatorInfo(SocialAccount $account): array
    {
        $this->ensureCanPublish($account);

        return $this->api->creatorInfo($account->getValidToken());
    }

    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload para TikTok. Post ID: {$post->id}");
        $post->update(['status' => ScheduledPostStatus::PROCESSING->value]);

        $this->ensureCanPublish($account);

        $token = $account->getValidToken();
        $payload = $post->payload ?? [];

        // A privacidade vale só se o TikTok ainda a oferece para a conta; nunca rebaixa em silêncio.
        $allowed = $this->api->creatorInfo($token)['privacy_level_options'] ?? [];
        if (! in_array($payload['privacy_level'] ?? null, $allowed, true)) {
            throw TikTokException::privacyNotAllowed();
        }

        // Já iniciou um Direct Post (job repetido): se o TikTok já recebeu o vídeo, só confere; nunca publica em duplicidade.
        if ($this->alreadySent($account, $post)) {
            return;
        }

        $size = $this->storageService->size($post->media_path);
        $totalChunks = max(1, intdiv($size, self::CHUNK_SIZE));
        $chunkSize = $totalChunks === 1 ? $size : self::CHUNK_SIZE;

        $init = $this->api->initUpload($token, $this->buildPostInfo($post), $size, $chunkSize, $totalChunks);

        // Grava o publish_id antes dos chunks: se o job morrer no meio, o status ainda pode ser conferido.
        $post->update(['container_id' => $init['publish_id']]);

        $this->sendChunks($init['upload_url'], $post->media_path, $size, $chunkSize, $totalChunks);

        CheckTikTokPostStatusJob::dispatch($post, $account, $init['publish_id']);

        Log::info("Vídeo do post {$post->id} enviado ao TikTok (publish_id {$init['publish_id']}). Aguardando confirmação.");
    }

    /** True quando o TikTok já tem o vídeo deste post (e o job de conferência foi despachado). */
    private function alreadySent(SocialAccount $account, ScheduledPost $post): bool
    {
        if (! $post->container_id) {
            return false;
        }

        $status = $this->api->fetchStatus($account->getValidToken(), $post->container_id)['status'] ?? null;

        // Envio incompleto ou falho: um novo init é seguro, o anterior nunca vai ao ar.
        if (in_array($status, [null, 'PROCESSING_UPLOAD', 'FAILED'], true)) {
            return false;
        }

        CheckTikTokPostStatusJob::dispatch($post, $account, $post->container_id);
        Log::warning("Post {$post->id}: o TikTok já recebeu o vídeo (publish_id {$post->container_id}). Reenvio evitado.");

        return true;
    }

    private function ensureCanPublish(SocialAccount $account): void
    {
        if (! $account->canPublish()) {
            throw TikTokException::scopeMissing();
        }
    }

    /** @return array<string, mixed> */
    private function buildPostInfo(ScheduledPost $post): array
    {
        $payload = $post->payload ?? [];
        $title = trim((string) ($post->caption ?: $post->title));

        return [
            'title' => mb_substr($title, 0, TikTokPayloadBuilder::MAX_TITLE_LENGTH),
            'privacy_level' => $payload['privacy_level'],
            'disable_comment' => (bool) ($payload['disable_comment'] ?? false),
            'disable_duet' => (bool) ($payload['disable_duet'] ?? false),
            'disable_stitch' => (bool) ($payload['disable_stitch'] ?? false),
            'brand_content_toggle' => (bool) ($payload['brand_content_toggle'] ?? false),
            'brand_organic_toggle' => (bool) ($payload['brand_organic_toggle'] ?? false),
        ];
    }

    private function sendChunks(string $uploadUrl, string $path, int $size, int $chunkSize, int $totalChunks): void
    {
        $mime = $this->storageService->mimeType($path);
        $mime = str_starts_with($mime, 'video/') ? $mime : 'video/mp4';
        $handle = $this->storageService->readStream($path);

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $start = $i * $chunkSize;
                // O último chunk absorve o resto da divisão.
                $end = $i === $totalChunks - 1 ? $size - 1 : $start + $chunkSize - 1;

                $this->api->uploadChunk($uploadUrl, $this->readExactly($handle, $end - $start + 1), $start, $end, $size, $mime);
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
        while (strlen($buffer) < $length && ! feof($handle)) {
            $data = fread($handle, $length - strlen($buffer));
            if ($data === false) {
                break;
            }
            $buffer .= $data;
        }

        if (strlen($buffer) !== $length) {
            Log::error("Leitura incompleta do vídeo no S3 (esperado {$length}, lido ".strlen($buffer).').');

            throw TikTokException::uploadFailed();
        }

        return $buffer;
    }
}
