<?php

namespace App\Exceptions;

use App\Enums\Exceptions\TikTokError;

class TikTokException extends BaseException
{
    public static function scopeMissing(): self
    {
        return static::make(TikTokError::SCOPE_MISSING);
    }

    public static function privacyNotAllowed(): self
    {
        return static::make(TikTokError::PRIVACY_NOT_ALLOWED);
    }

    public static function invalidResponse(): self
    {
        return static::make(TikTokError::INVALID_RESPONSE);
    }

    public static function uploadFailed(): self
    {
        return static::make(TikTokError::UPLOAD_FAILED);
    }

    public static function apiUnavailable(): self
    {
        return static::make(TikTokError::API_UNAVAILABLE);
    }

    public static function publishTimeout(): self
    {
        return static::make(TikTokError::PUBLISH_TIMEOUT);
    }

    public function isPermanent(): bool
    {
        return TikTokError::tryFrom($this->errorCode)?->isPermanent() ?? false;
    }

    /** Código do TikTok (`error.code` ou `fail_reason`) -> mensagem pt-BR. O texto cru nunca passa daqui. */
    public static function fromApiCode(?string $code): self
    {
        return static::make(TikTokError::fromApiCode($code));
    }
}
