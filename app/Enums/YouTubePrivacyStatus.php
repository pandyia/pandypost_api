<?php

namespace App\Enums;

enum YouTubePrivacyStatus: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case UNLISTED = 'unlisted';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Público',
            self::PRIVATE => 'Privado',
            self::UNLISTED => 'Não listado',
        };
    }
}
