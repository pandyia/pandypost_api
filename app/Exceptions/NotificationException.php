<?php

namespace App\Exceptions;

use App\Enums\Exceptions\NotificationError;

class NotificationException extends BaseException
{
    public static function notFound(): self
    {
        return static::make(NotificationError::NOT_FOUND);
    }
}
