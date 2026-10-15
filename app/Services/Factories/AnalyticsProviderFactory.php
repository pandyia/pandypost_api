<?php

namespace App\Services\Factories;

use App\Contracts\AnalyticsProviderInterface;
use App\Enums\Platform;
use App\Services\Analytics\InstagramAnalyticsProvider;
use App\Services\Analytics\TikTokAnalyticsProvider;
use App\Services\Analytics\YouTubeAnalyticsProvider;

class AnalyticsProviderFactory
{
    public function make(Platform $platform): AnalyticsProviderInterface
    {
        return match ($platform) {
            Platform::YOUTUBE => app(YouTubeAnalyticsProvider::class),
            Platform::INSTAGRAM => app(InstagramAnalyticsProvider::class),
            Platform::TIKTOK => app(TikTokAnalyticsProvider::class),
        };
    }
}
