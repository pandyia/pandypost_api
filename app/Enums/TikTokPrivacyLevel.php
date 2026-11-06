<?php

namespace App\Enums;

/** Valores de `privacy_level` aceitos pela Content Posting API do TikTok. */
enum TikTokPrivacyLevel: string
{
    case PUBLIC_TO_EVERYONE = 'PUBLIC_TO_EVERYONE';
    case MUTUAL_FOLLOW_FRIENDS = 'MUTUAL_FOLLOW_FRIENDS';
    case FOLLOWER_OF_CREATOR = 'FOLLOWER_OF_CREATOR';
    case SELF_ONLY = 'SELF_ONLY';
}
