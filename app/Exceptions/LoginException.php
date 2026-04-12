<?php

namespace App\Exceptions;

use App\Enums\Exceptions\LoginError;

class LoginException extends BaseException
{
    public static function invalidCredentials(): self
    {
        $error = LoginError::INVALID_CREDENTIALS;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function userNotFound(): self
    {
        $error = LoginError::USER_NOT_FOUND;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function accountDisabled(): self
    {
        $error = LoginError::ACCOUNT_DISABLED;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function emailNotVerified(): self
    {
        $error = LoginError::EMAIL_NOT_VERIFIED;
        return new self($error->message(), $error, $error->httpCode());
    }
}
