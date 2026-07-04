<?php

namespace App\Services\Payloads\Builders;

class InstagramPayloadBuilder extends AbstractPlatformPayloadBuilder
{
    protected function extractPayload(array &$attributes): array
    {
        $payload = parent::extractPayload($attributes);

        // Instagram atualmente só utiliza caption (campo direto do ScheduledPost)
        // e media_type (REELS vs image) que é inferido automaticamente pelo InstagramService.
        // Campos extras como hashtags automáticas, location, collaborators
        // podem ser adicionados aqui no futuro.

        return $payload;
    }
}
