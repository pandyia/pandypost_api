<?php

namespace App\Exceptions;

use App\Enums\Exceptions\RoleError;

class RoleException extends BaseException
{
    public static function nameAlreadyExists(): self
    {
        $error = RoleError::ROLE_NAME_ALREADY_EXISTS;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function hasLinkedUsers(): self
    {
        $error = RoleError::PROFILE_HAS_LINKED_USERS;
        return new self($error->message(), $error, $error->httpCode());
    }
}
