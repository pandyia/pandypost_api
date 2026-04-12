<?php

namespace App\Exceptions;

use App\Enums\Exceptions\ScheduledPostError;

class ScheduledPostException extends BaseException
{
    public static function noAccountLinked(string $platform): self
    {
        $error = ScheduledPostError::NO_ACCOUNT_LINKED;
        return new self($error->message($platform), $error, $error->httpCode());
    }

    public static function platformNotSupported(string $platform): self
    {
        $error = ScheduledPostError::PLATFORM_NOT_SUPPORTED;
        return new self($error->message($platform), $error, $error->httpCode());
    }

    public static function invalidAccountSelection(string $platform): self
    {
        $error = ScheduledPostError::INVALID_ACCOUNT_SELECTION;
        return new self($error->message($platform), $error, $error->httpCode());
    }
}
