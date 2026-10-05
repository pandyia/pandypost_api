<?php

namespace App\Enums;

enum TikTokPrivacyLevel: string
{
    case PUBLIC_TO_EVERYONE = 'PUBLIC_TO_EVERYONE';
    case MUTUAL_FOLLOW_FRIENDS = 'MUTUAL_FOLLOW_FRIENDS';
    case FOLLOWER_OF_CREATOR = 'FOLLOWER_OF_CREATOR';
    case SELF_ONLY = 'SELF_ONLY';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC_TO_EVERYONE => 'Público',
            self::MUTUAL_FOLLOW_FRIENDS => 'Amigos',
            self::FOLLOWER_OF_CREATOR => 'Seguidores',
            self::SELF_ONLY => 'Somente eu',
        };
    }
}
