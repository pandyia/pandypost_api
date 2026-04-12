<?php

namespace App\Exceptions;

use App\Enums\Exceptions\SocialAccountError;

class SocialAccountException extends BaseException
{
    public static function authFailed(string $platform): self
    {
        $error = SocialAccountError::AUTH_FAILED;
        return new self($error->message($platform), $error, $error->httpCode());
    }

    public static function invalidCode(): self
    {
        $error = SocialAccountError::INVALID_CODE;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function tokenExchangeFailed(): self
    {
        $error = SocialAccountError::TOKEN_EXCHANGE_FAILED;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function platformNotSupported(string $platform): self
    {
        $error = SocialAccountError::PLATFORM_NOT_SUPPORTED;
        return new self($error->message($platform), $error, $error->httpCode());
    }

    public static function accountAlreadyLinked(string $platform): self
    {
        $error = SocialAccountError::ACCOUNT_ALREADY_LINKED;
        return new self($error->message($platform), $error, $error->httpCode());
    }

    public static function invalidOAuthState(): self
    {
        $error = SocialAccountError::INVALID_OAUTH_STATE;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function oauthTokenExchangeFailed(): self
    {
        $error = SocialAccountError::OAUTH_TOKEN_EXCHANGE_FAILED;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function oauthInitializationFailed(): self
    {
        $error = SocialAccountError::OAUTH_INITIALIZATION_FAILED;
        return new self($error->message(), $error, $error->httpCode());
    }
}
