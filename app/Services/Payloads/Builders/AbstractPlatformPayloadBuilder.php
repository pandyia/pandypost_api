<?php

namespace App\Services\Payloads\Builders;

use App\Contracts\PlatformPayloadBuilderInterface;
use App\Services\Payloads\PayloadBuildResult;

abstract class AbstractPlatformPayloadBuilder implements PlatformPayloadBuilderInterface
{
    public function build(array $input): PayloadBuildResult
    {
        $attributes = $input;
        $payload = $this->extractPayload($attributes);

        // A thumbnail agora chega como um path do S3 no $input, não como UploadedFile.
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
