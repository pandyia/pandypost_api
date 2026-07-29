<?php

namespace App\Exceptions;

use App\Enums\Exceptions\GeneralError;

class GeneralException extends BaseException
{
    public static function tooManyAttempts(): self
    {
        return static::make(GeneralError::TOO_MANY_ATTEMPTS);
    }
}
