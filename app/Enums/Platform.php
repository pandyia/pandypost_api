<?php

namespace App\Enums;

enum Platform: string
{
    case YOUTUBE = 'youtube';
    case TIKTOK = 'tiktok';
    case INSTAGRAM = 'instagram';

    public function label(): string
    {
        return match ($this) {
            self::YOUTUBE => 'YouTube',
            self::TIKTOK => 'TikTok',
            self::INSTAGRAM => 'Instagram',
        };
    }
}
