<?php

namespace App\Services\Payloads\Builders;

class TikTokPayloadBuilder extends AbstractPlatformPayloadBuilder
{
    protected function extractPayload(array &$attributes): array
    {
        $payload = parent::extractPayload($attributes);

        $privacyLevel = $this->pull($attributes, 'tiktok_privacy_level');
        if ($privacyLevel !== null && $privacyLevel !== '') {
            $payload['tiktok_privacy_level'] = (string) $privacyLevel;
        }

        $disableComment = $this->normalizeBoolean($this->pull($attributes, 'tiktok_disable_comment'));
        if ($disableComment !== null) {
            $payload['tiktok_disable_comment'] = $disableComment;
        }

        $disableDuet = $this->normalizeBoolean($this->pull($attributes, 'tiktok_disable_duet'));
        if ($disableDuet !== null) {
            $payload['tiktok_disable_duet'] = $disableDuet;
        }

        $disableStitch = $this->normalizeBoolean($this->pull($attributes, 'tiktok_disable_stitch'));
        if ($disableStitch !== null) {
            $payload['tiktok_disable_stitch'] = $disableStitch;
        }

        $brandToggle = $this->normalizeBoolean($this->pull($attributes, 'tiktok_brand_content_toggle'));
        if ($brandToggle !== null) {
            $payload['tiktok_brand_content_toggle'] = $brandToggle;
        }

        return $payload;
    }
}
