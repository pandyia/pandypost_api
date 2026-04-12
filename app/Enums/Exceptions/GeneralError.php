<?php

namespace App\Enums\Exceptions;

enum GeneralError: string
{
    case TOO_MANY_ATTEMPTS = 'TOO_MANY_ATTEMPTS';

    public function message(): string
    {
        return match ($this) {
            self::TOO_MANY_ATTEMPTS => 'Muitas tentativas. Por favor, tente novamente mais tarde.',
        };
    }
}
