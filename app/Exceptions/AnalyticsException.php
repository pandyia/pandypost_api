<?php

namespace App\Exceptions;

use App\Enums\Exceptions\AnalyticsError;
use App\Enums\Platform;

class AnalyticsException extends BaseException
{
    public static function reauthRequired(Platform $platform): self
    {
        $error = AnalyticsError::REAUTH_REQUIRED;

        return static::make($error, $error->message($platform->label()));
    }

    public static function providerUnavailable(Platform $platform): self
    {
        $error = AnalyticsError::PROVIDER_UNAVAILABLE;

        return static::make($error, $error->message($platform->label()));
    }

    public static function refreshTooSoon(int $minutes): self
    {
        $error = AnalyticsError::REFRESH_TOO_SOON;

        return static::make($error, $error->message((string) $minutes));
    }
}
