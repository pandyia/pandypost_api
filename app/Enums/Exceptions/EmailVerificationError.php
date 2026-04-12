<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum EmailVerificationError: string implements ErrorEnumInterface
{
    case INVALID_TOKEN = 'invalid_token';
    case EXPIRED_TOKEN = 'expired_token';
    case ALREADY_VERIFIED = 'already_verified';

    public function message(): string
    {
        return match ($this) {
            self::INVALID_TOKEN => 'Token inválido.',
            self::EXPIRED_TOKEN => 'Token expirado.',
            self::ALREADY_VERIFIED => 'Email já verificado.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::INVALID_TOKEN => 400,
            self::EXPIRED_TOKEN => 400,
            self::ALREADY_VERIFIED => 409,
        };
    }
}
