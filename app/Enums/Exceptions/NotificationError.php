<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum NotificationError: string implements ErrorEnumInterface
{
    case NOT_FOUND = 'notification_not_found';

    public function message(): string
    {
        return match ($this) {
            self::NOT_FOUND => 'Notificação não encontrada.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::NOT_FOUND => 404,
        };
    }
}
