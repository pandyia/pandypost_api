<?php

namespace App\Services\Factories;

use App\Contracts\SocialMediaServiceInterface;
use App\Enums\Platform;
use App\Services\InstagramService;
use App\Services\TikTokService;
use App\Services\YouTubeService;

class SocialMediaFactory
{
    public function make(Platform $platform): SocialMediaServiceInterface
    {
        return match ($platform) {
            Platform::YOUTUBE   => app(YouTubeService::class),
            Platform::INSTAGRAM => app(InstagramService::class),
            Platform::TIKTOK    => app(TikTokService::class),
        };
    }
}
