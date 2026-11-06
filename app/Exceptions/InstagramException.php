<?php

namespace App\Exceptions;

use App\Enums\Exceptions\InstagramError;

class InstagramException extends BaseException
{
    /** Código da Graph API (`error.code`) -> mensagem pt-BR. O texto cru da Meta nunca passa daqui. */
    public static function fromApiCode(?int $code): self
    {
        return static::make(InstagramError::fromApiCode($code));
    }

    public static function connectionFailed(): self
    {
        return static::make(InstagramError::API_UNAVAILABLE);
    }

    public static function invalidResponse(): self
    {
        return static::make(InstagramError::INVALID_RESPONSE);
    }

    public static function containerFailed(): self
    {
        return static::make(InstagramError::CONTAINER_FAILED);
    }

    public static function publishTimeout(): self
    {
        return static::make(InstagramError::PUBLISH_TIMEOUT);
    }

    public function isPermanent(): bool
    {
        return InstagramError::tryFrom($this->errorCode)?->isPermanent() ?? false;
    }

    /** Sem resposta da Meta: o resultado da chamada é desconhecido, então quem chama não deve dar o post como falho. */
    public function isUnavailable(): bool
    {
        return $this->errorCode === InstagramError::API_UNAVAILABLE->value;
    }
}
