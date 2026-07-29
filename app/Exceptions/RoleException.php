<?php

namespace App\Exceptions;

use App\Enums\Exceptions\RoleError;

class RoleException extends BaseException
{
    public static function nameAlreadyExists(): self
    {
        return static::make(RoleError::ROLE_NAME_ALREADY_EXISTS);
    }

    public static function hasLinkedUsers(): self
    {
        return static::make(RoleError::PROFILE_HAS_LINKED_USERS);
    }
}
