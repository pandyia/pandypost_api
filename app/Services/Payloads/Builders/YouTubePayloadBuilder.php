<?php

namespace App\Services\Payloads\Builders;

use App\Enums\YouTubePrivacyStatus;
use Illuminate\Support\Facades\Validator;

class YouTubePayloadBuilder extends AbstractPlatformPayloadBuilder
{
    // Limite da descrição do YouTube (em bytes, não em caracteres).
    private const MAX_DESCRIPTION_BYTES = 5000;

    protected function extractPayload(array &$attributes): array
    {
        $payload = parent::extractPayload($attributes);

        $this->validateMediaExtension($attributes, ['mp4', 'mov'], 'O YouTube aceita apenas vídeo (MP4 ou MOV).');

        Validator::make($attributes, [
            'title' => ['required', 'string', 'max:100'],
            'caption' => ['nullable', 'string', 'max:5000', fn ($attribute, $value, $fail) => strlen((string) $value) > self::MAX_DESCRIPTION_BYTES
                ? $fail('A descrição do YouTube pode ter no máximo 5.000 bytes.') : null],
        ], [
            'title.required' => 'Para postar no YouTube, você precisa definir um título.',
            'title.max' => 'O título do YouTube pode ter no máximo 100 caracteres.',
            'caption.max' => 'A descrição do YouTube pode ter no máximo 5.000 caracteres.',
        ])->validate();

        $isShort = $this->normalizeBoolean($this->pull($attributes, 'is_short'));
        if ($isShort !== null) {
            $payload['is_short'] = $isShort;
        }

        $privacyStatus = $this->pull($attributes, 'youtube_privacy_status');
        if ($privacyStatus !== null && $privacyStatus !== '') {
            $payload['youtube_privacy_status'] = $this->normalizePrivacyStatus($privacyStatus);
        }

        $categoryId = $this->pull($attributes, 'youtube_category_id');
        if ($categoryId !== null && $categoryId !== '') {
            $payload['youtube_category_id'] = (string) $categoryId;
        }

        $tags = $this->normalizeStringArray($this->pull($attributes, 'youtube_tags', []));
        if ($tags !== []) {
            $payload['youtube_tags'] = $tags;
        }

        $madeForKids = $this->normalizeBoolean($this->pull($attributes, 'youtube_made_for_kids'));
        if ($madeForKids !== null) {
            $payload['youtube_made_for_kids'] = $madeForKids;
        }

        return $payload;
    }

    private function normalizePrivacyStatus(mixed $value): string
    {
        if ($value instanceof YouTubePrivacyStatus) {
            return $value->value;
        }

        return (string) $value;
    }
}
