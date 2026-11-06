<?php

namespace App\Enums;

enum Platform: string
{
    case YOUTUBE = 'youtube';
    case TIKTOK = 'tiktok';
    case INSTAGRAM = 'instagram';

    /**
     * Escopo OAuth que a conta precisa ter para publicar. `null` = a rede não exige (ainda).
     */
    public function publishScope(): ?string
    {
        return match ($this) {
            self::TIKTOK => 'video.publish',
            self::YOUTUBE, self::INSTAGRAM => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::YOUTUBE => 'YouTube',
            self::TIKTOK => 'TikTok',
            self::INSTAGRAM => 'Instagram',
        };
    }
}
