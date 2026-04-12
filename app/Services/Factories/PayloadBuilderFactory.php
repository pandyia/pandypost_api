<?php

namespace App\Services\Factories;

use App\Contracts\PlatformPayloadBuilderInterface;
use App\Enums\Platform;
use App\Services\Payloads\Builders\InstagramPayloadBuilder;
use App\Services\Payloads\Builders\TikTokPayloadBuilder;
use App\Services\Payloads\Builders\YouTubePayloadBuilder;

class PayloadBuilderFactory
{
    public function make(Platform $platform): PlatformPayloadBuilderInterface
    {
        return match ($platform) {
            Platform::YOUTUBE   => app(YouTubePayloadBuilder::class),
            Platform::INSTAGRAM => app(InstagramPayloadBuilder::class),
            Platform::TIKTOK    => app(TikTokPayloadBuilder::class),
        };
    }
}
