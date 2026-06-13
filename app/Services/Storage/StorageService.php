<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    /**
     * Gera uma presigned PUT URL para o client fazer upload direto no S3.
     *
     * @param string $directory  Diretório lógico (ex: 'videos', 'thumbnails')
     * @param string $workspaceUuid  UUID do workspace para prefixo de isolamento
     * @param string $contentType  MIME type permitido (ex: 'video/mp4')
     * @param string $extension  Extensão do arquivo (ex: 'mp4')
     * @return array{url: string, path: string, content_type: string, max_size: int, expires_in: int}
     */
    public function generateUploadUrl(
        string $directory,
        string $workspaceUuid,
        string $contentType,
        string $extension,
    ): array {
        $uuid = (string) Str::uuid();
        $path = "workspaces/{$workspaceUuid}/{$directory}/{$uuid}.{$extension}";
        $ttl  = (int) config('services.s3.presigned_put_ttl', 86400);

        $url = Storage::disk('s3')->temporaryUploadUrl($path, now()->addSeconds($ttl), [
            'ContentType' => $contentType,
        ]);

        return [
            'url'          => $url['url'],
            'headers'      => $url['headers'] ?? [],
            'path'         => $path,
            'content_type' => $contentType,
            'max_size'     => (int) config('services.s3.max_upload_size', 32212254720),
            'expires_in'   => $ttl,
        ];
    }

    /**
     * Gera uma presigned GET URL para o client (ou API de plataforma) baixar direto do S3.
     */
    public function generateDownloadUrl(string $path, ?int $ttl = null): string
    {
        $ttl = $ttl ?? (int) config('services.s3.presigned_get_ttl', 1800);

        return Storage::disk('s3')->temporaryUrl($path, now()->addSeconds($ttl));
    }

    /**
     * Verifica se o path existe no S3 (HEAD request).
     */
    public function exists(string $path): bool
    {
        return Storage::disk('s3')->exists($path);
    }

    /**
     * Deleta um objeto do S3.
     */
    public function delete(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }

    /**
     * Deleta múltiplos objetos do S3.
     */
    public function deleteMany(array $paths): bool
    {
        $paths = array_filter($paths);

        if (empty($paths)) {
            return true;
        }

        return Storage::disk('s3')->delete($paths);
    }

    /**
     * Retorna o tamanho de um arquivo no S3.
     */
    public function size(string $path): int
    {
        return Storage::disk('s3')->size($path);
    }

    /**
     * Retorna o mime type de um arquivo no S3.
     */
    public function mimeType(string $path): string
    {
        return Storage::disk('s3')->mimeType($path) ?? 'application/octet-stream';
    }

    /**
     * Abre um read stream de um arquivo no S3.
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        return Storage::disk('s3')->readStream($path);
    }

    /**
     * Valida se o path pertence ao workspace informado.
     */
    public function pathBelongsToWorkspace(string $path, string $workspaceUuid): bool
    {
        return str_starts_with($path, "workspaces/{$workspaceUuid}/");
    }

    /**
     * Troca o storage path de um model: deleta o antigo se existir, retorna o novo.
     * Centraliza a lógica de mutação para evitar storage leak.
     */
    public function replaceFile(?string $oldPath, ?string $newPath): ?string
    {
        if ($oldPath && $oldPath !== $newPath) {
            $this->delete($oldPath);
        }

        return $newPath;
    }
}
