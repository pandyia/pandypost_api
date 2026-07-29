<?php

namespace App\Exceptions;

use App\Enums\Exceptions\EmailVerificationError;

class TokenException extends BaseException
{
    public static function invalidToken(): self
    {
        return static::make(EmailVerificationError::INVALID_TOKEN);
    }

    public static function expiredToken(): self
    {
        return static::make(EmailVerificationError::EXPIRED_TOKEN);
    }

    public static function alreadyVerified(): self
    {
        return static::make(EmailVerificationError::ALREADY_VERIFIED);
    }
}
