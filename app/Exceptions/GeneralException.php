<?php

namespace App\Exceptions;

use App\Enums\Exceptions\GeneralError;

class GeneralException extends BaseException
{
    public static function tooManyAttempts(): self
    {
        return new self(
            GeneralError::TOO_MANY_ATTEMPTS->message(),
            GeneralError::TOO_MANY_ATTEMPTS,
            429
        );
    }
}
