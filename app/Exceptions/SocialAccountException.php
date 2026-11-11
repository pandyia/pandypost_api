<?php

namespace App\Exceptions;

use App\Enums\Exceptions\SocialAccountError;

class SocialAccountException extends BaseException
{
    public static function authFailed(string $platform): self
    {
        $error = SocialAccountError::AUTH_FAILED;

        return static::make($error, $error->message($platform));
    }

    public static function invalidCode(): self
    {
        return static::make(SocialAccountError::INVALID_CODE);
    }

    public static function tokenExchangeFailed(): self
    {
        return static::make(SocialAccountError::TOKEN_EXCHANGE_FAILED);
    }

    public static function platformNotSupported(string $platform): self
    {
        $error = SocialAccountError::PLATFORM_NOT_SUPPORTED;

        return static::make($error, $error->message($platform));
    }

    public static function invalidOAuthState(): self
    {
        return static::make(SocialAccountError::INVALID_OAUTH_STATE);
    }

    public static function oauthStateContextInvalid(): self
    {
        return static::make(SocialAccountError::OAUTH_STATE_CONTEXT_INVALID);
    }

    public static function connectPermissionDenied(): self
    {
        return static::make(SocialAccountError::CONNECT_PERMISSION_DENIED);
    }

    public static function oauthTokenExchangeFailed(): self
    {
        return static::make(SocialAccountError::OAUTH_TOKEN_EXCHANGE_FAILED);
    }

    public static function oauthInitializationFailed(): self
    {
        return static::make(SocialAccountError::OAUTH_INITIALIZATION_FAILED);
    }

    public static function refreshTokenMissing(): self
    {
        return static::make(SocialAccountError::REFRESH_TOKEN_MISSING);
    }

    public static function tokenRefreshFailed(string $provider): self
    {
        // Resposta de terceiro (body) nunca entra na mensagem: ela vai para error_message e para o front.
        return static::make(SocialAccountError::TOKEN_REFRESH_FAILED, "Não foi possível renovar o token do {$provider}.");
    }
}
