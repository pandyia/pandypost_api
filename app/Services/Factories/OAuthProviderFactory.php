<?php

namespace App\Services\Factories;

use App\Contracts\OAuthProviderInterface;
use App\Exceptions\SocialAccountException;
use App\Services\OAuthProviders\GoogleOAuthProvider;
use App\Services\OAuthProviders\InstagramOAuthProvider;
use App\Services\OAuthProviders\TikTokOAuthProvider;

class OAuthProviderFactory
{
    /**
     * A plataforma vem da URL (/social-accounts/{platform}/auth); o YouTube conecta pelo Google.
     */
    public function make(string $platform): OAuthProviderInterface
    {
        return match ($platform) {
            'google' => app(GoogleOAuthProvider::class),
            'instagram' => app(InstagramOAuthProvider::class),
            'tiktok' => app(TikTokOAuthProvider::class),
            default => throw SocialAccountException::platformNotSupported($platform),
        };
    }
}
