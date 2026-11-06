<?php

namespace App\Services\Factories;

use App\Contracts\PlatformTokenApiInterface;
use App\Contracts\SocialMediaServiceInterface;
use App\Enums\Platform;
use App\Services\Instagram\InstagramApi;
use App\Services\InstagramService;
use App\Services\TikTok\TikTokApi;
use App\Services\TikTokService;
use App\Services\YouTube\YouTubeApi;
use App\Services\YouTubeService;

class SocialMediaFactory
{
    public function make(Platform $platform): SocialMediaServiceInterface
    {
        return match ($platform) {
            Platform::YOUTUBE => app(YouTubeService::class),
            Platform::INSTAGRAM => app(InstagramService::class),
            Platform::TIKTOK => app(TikTokService::class),
        };
    }

    public function tokenApi(Platform $platform): PlatformTokenApiInterface
    {
        return match ($platform) {
            Platform::YOUTUBE => app(YouTubeApi::class),
            Platform::INSTAGRAM => app(InstagramApi::class),
            Platform::TIKTOK => app(TikTokApi::class),
        };
    }
}
