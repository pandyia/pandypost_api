<?php

namespace App\Services\Payloads\Builders;

use Illuminate\Support\Facades\Validator;

// O Instagram só usa a legenda (campo direto do ScheduledPost); o tipo de mídia é inferido no InstagramService.
class InstagramPayloadBuilder extends AbstractPlatformPayloadBuilder
{
    // Limite da legenda do Instagram.
    public const MAX_CAPTION_LENGTH = 2200;

    protected function extractPayload(array &$attributes): array
    {
        // Reels aceita MP4/MOV; imagem, só JPEG (a Graph API recusa PNG).
        $this->validateMediaExtension($attributes, ['mp4', 'mov', 'jpg', 'jpeg'], 'O Instagram aceita vídeo (MP4 ou MOV) ou imagem JPEG. PNG não é aceito.');

        Validator::make($attributes, [
            'caption' => ['nullable', 'string', 'max:'.self::MAX_CAPTION_LENGTH],
        ], [
            'caption.max' => 'A legenda do Instagram pode ter no máximo '.self::MAX_CAPTION_LENGTH.' caracteres.',
        ])->validate();

        return parent::extractPayload($attributes);
    }
}
