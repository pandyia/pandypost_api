<?php

namespace App\Services\Payloads\Builders;

use App\Contracts\PlatformPayloadBuilderInterface;
use App\Services\Payloads\PayloadBuildResult;
use Illuminate\Http\UploadedFile;

abstract class AbstractPlatformPayloadBuilder implements PlatformPayloadBuilderInterface
{
    public function build(array $input, ?UploadedFile $thumbnail = null): PayloadBuildResult
    {
        $attributes = $input;
        $payload = $this->extractPayload($attributes);

        if ($thumbnail) {
            $payload['thumbnail_path'] = $this->storeThumbnail($thumbnail);
        }

        unset($attributes['video'], $attributes['thumbnail']);

        return new PayloadBuildResult($attributes, $payload);
    }

    protected function extractPayload(array &$attributes): array
    {
        return $attributes['payload'] ?? [];
    }

    protected function storeThumbnail(UploadedFile $thumbnail): string
    {
        return $thumbnail->store('thumbnails');
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
