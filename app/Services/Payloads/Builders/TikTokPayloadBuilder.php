<?php

namespace App\Services\Payloads\Builders;

class TikTokPayloadBuilder extends AbstractPlatformPayloadBuilder
{
    private const BOOLEAN_FIELDS = [
        'tiktok_disable_comment',
        'tiktok_disable_duet',
        'tiktok_disable_stitch',
        'tiktok_brand_content_toggle',
    ];

    protected function extractPayload(array &$attributes): array
    {
        $payload = parent::extractPayload($attributes);

        $privacyLevel = $this->pull($attributes, 'tiktok_privacy_level');
        if ($privacyLevel !== null && $privacyLevel !== '') {
            $payload['tiktok_privacy_level'] = (string) $privacyLevel;
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            $value = $this->normalizeBoolean($this->pull($attributes, $field));
            if ($value !== null) {
                $payload[$field] = $value;
            }
        }

        return $payload;
    }
}
