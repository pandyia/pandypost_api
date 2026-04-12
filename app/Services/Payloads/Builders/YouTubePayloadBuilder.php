<?php

namespace App\Services\Payloads\Builders;

use App\Enums\YouTubePrivacyStatus;

class YouTubePayloadBuilder extends AbstractPlatformPayloadBuilder
{
    protected function extractPayload(array &$attributes): array
    {
        $payload = parent::extractPayload($attributes);

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
