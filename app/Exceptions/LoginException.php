<?php

namespace App\Exceptions;

use App\Enums\Exceptions\LoginError;

class LoginException extends BaseException
{
    public static function invalidCredentials(): self
    {
        return static::make(LoginError::INVALID_CREDENTIALS);
    }

    public static function userNotFound(): self
    {
        return static::make(LoginError::USER_NOT_FOUND);
    }

    public static function accountDisabled(): self
    {
        return static::make(LoginError::ACCOUNT_DISABLED);
    }

    public static function emailNotVerified(): self
    {
        return static::make(LoginError::EMAIL_NOT_VERIFIED);
    }
}
