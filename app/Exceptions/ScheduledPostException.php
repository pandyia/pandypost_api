<?php

namespace App\Exceptions;

use App\Enums\Exceptions\ScheduledPostError;

class ScheduledPostException extends BaseException
{
    public static function noAccountLinked(string $platform): self
    {
        $error = ScheduledPostError::NO_ACCOUNT_LINKED;
        return static::make($error, $error->message($platform));
    }

    public static function platformNotSupported(string $platform): self
    {
        $error = ScheduledPostError::PLATFORM_NOT_SUPPORTED;
        return static::make($error, $error->message($platform));
    }

    public static function invalidAccountSelection(string $platform): self
    {
        $error = ScheduledPostError::INVALID_ACCOUNT_SELECTION;
        return static::make($error, $error->message($platform));
    }

    public static function cannotCancel(): self
    {
        return static::make(ScheduledPostError::CANCEL_NOT_ALLOWED);
    }
}
