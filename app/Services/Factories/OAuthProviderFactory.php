<?php

namespace App\Services\Factories;

use App\Contracts\OAuthProviderInterface;
use App\Services\OAuthProviders\DefaultSocialiteProvider;
use App\Services\OAuthProviders\GoogleOAuthProvider;
use App\Services\OAuthProviders\InstagramOAuthProvider;
use App\Services\OAuthProviders\TikTokOAuthProvider;
use InvalidArgumentException;

class OAuthProviderFactory
{
    public function make(string $platform): OAuthProviderInterface
    {
        return match ($platform) {
            'instagram' => app(InstagramOAuthProvider::class),
            'google'    => app(GoogleOAuthProvider::class),
            'tiktok'    => app(TikTokOAuthProvider::class),
            default     => new DefaultSocialiteProvider($platform),
        };
    }
}
