<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum LoginError: string implements ErrorEnumInterface
{
    case INVALID_CREDENTIALS = 'invalid_credentials';
    case USER_NOT_FOUND = 'user_not_found';
    case ACCOUNT_DISABLED = 'account_disabled';
    case EMAIL_NOT_VERIFIED = 'email_not_verified';

    public function message(): string
    {
        return match ($this) {
            self::INVALID_CREDENTIALS => 'Credenciais inválidas.',
            self::USER_NOT_FOUND => 'Usuário não encontrado.',
            self::ACCOUNT_DISABLED => 'Conta desativada.',
            self::EMAIL_NOT_VERIFIED => 'Confirme seu email para continuar!',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::INVALID_CREDENTIALS => 401,
            self::USER_NOT_FOUND => 404,
            self::ACCOUNT_DISABLED => 403,
            self::EMAIL_NOT_VERIFIED => 403,
        };
    }
}
