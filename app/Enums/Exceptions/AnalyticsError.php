<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum AnalyticsError: string implements ErrorEnumInterface
{
    case REAUTH_REQUIRED = 'analytics_reauth_required';
    case PROVIDER_UNAVAILABLE = 'analytics_provider_unavailable';
    case REFRESH_TOO_SOON = 'analytics_refresh_too_soon';

    public function message(?string $context = null): string
    {
        return match ($this) {
            self::REAUTH_REQUIRED => "Reconecte a conta do {$context} para liberar a leitura das métricas.",
            self::PROVIDER_UNAVAILABLE => "Não foi possível carregar as métricas do {$context} agora. Tente novamente em alguns minutos.",
            self::REFRESH_TOO_SOON => "Você já atualizou os dados recentemente. Tente novamente em {$context} minutos.",
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::REAUTH_REQUIRED => 409,
            self::PROVIDER_UNAVAILABLE => 502,
            self::REFRESH_TOO_SOON => 429,
        };
    }
}
