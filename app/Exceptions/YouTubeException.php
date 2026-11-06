<?php

namespace App\Exceptions;

use App\Enums\Exceptions\YouTubeError;
use Google\Service\Exception as GoogleServiceException;

class YouTubeException extends BaseException
{
    public static function refreshTokenMissing(): self
    {
        return static::make(YouTubeError::REFRESH_TOKEN_MISSING);
    }

    public static function tokenRefreshFailed(): self
    {
        return static::make(YouTubeError::TOKEN_REFRESH_FAILED);
    }

    public static function uploadFailed(): self
    {
        return static::make(YouTubeError::UPLOAD_FAILED);
    }

    public function isPermanent(): bool
    {
        return YouTubeError::tryFrom($this->errorCode)?->isPermanent() ?? false;
    }

    /** Erro da API do Google -> mensagem pt-BR pelo `reason`. O JSON cru da resposta nunca passa daqui. */
    public static function fromGoogle(GoogleServiceException $e): self
    {
        return static::make(YouTubeError::fromReason(static::reasonOf($e)));
    }

    /** Único dado de terceiro que pode ir para o log. */
    public static function reasonOf(GoogleServiceException $e): ?string
    {
        return $e->getErrors()[0]['reason'] ?? null;
    }
}
