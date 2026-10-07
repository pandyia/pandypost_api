<?php

namespace App\Services\Payloads\Builders;

use App\Contracts\PlatformPayloadBuilderInterface;
use App\Services\Payloads\PayloadBuildResult;

abstract class AbstractPlatformPayloadBuilder implements PlatformPayloadBuilderInterface
{
    // Separa os campos do post (attributes) das opções da plataforma (payload).
    public function build(array $input): PayloadBuildResult
    {
        $attributes = $input;
        $payload = $this->extractPayload($attributes);

        // A thumbnail já está no S3; só o caminho dela vai para o payload.
        $thumbnailPath = $this->pull($attributes, 'thumbnail_storage_path');
        if ($thumbnailPath) {
            $payload['thumbnail_path'] = $thumbnailPath;
        }

        unset($attributes['media_storage_path'], $attributes['thumbnail_storage_path']);

        return new PayloadBuildResult($attributes, $payload);
    }

    protected function extractPayload(array &$attributes): array
    {
        return $attributes['payload'] ?? [];
    }

    // Converte "true", "1", "on" etc. vindos do request; valor inválido vira $default.
    protected function normalizeBoolean(mixed $value, ?bool $default = null): ?bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    protected function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value
        ), static fn (string $item): bool => $item !== ''));
    }

    // Tira a chave do array e devolve o valor, para ela não sobrar nos attributes.
    protected function pull(array &$attributes, string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $attributes)) {
            return $default;
        }

        $value = $attributes[$key];
        unset($attributes[$key]);

        return $value;
    }
}
