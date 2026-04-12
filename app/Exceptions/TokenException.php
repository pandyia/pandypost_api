<?php

namespace App\Exceptions;

use App\Enums\Exceptions\EmailVerificationError;

class TokenException extends BaseException
{
    public static function invalidToken(): self
    {
        $error = EmailVerificationError::INVALID_TOKEN;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function expiredToken(): self
    {
        $error = EmailVerificationError::EXPIRED_TOKEN;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function alreadyVerified(): self
    {
        $error = EmailVerificationError::ALREADY_VERIFIED;
        return new self($error->message(), $error, $error->httpCode());
    }
}
