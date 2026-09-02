<?php

namespace App\Exceptions;

use App\Enums\Exceptions\UserError;

class UserException extends BaseException
{
    public static function cannotRemoveYourself(): self
    {
        return static::make(UserError::CANNOT_REMOVE_YOURSELF);
    }

    public static function workspaceMustHaveAtLeastOneUser(): self
    {
        return static::make(UserError::WORKSPACE_MUST_HAVE_AT_LEAST_ONE_USER);
    }

    public static function profileNotFound(): self
    {
        return static::make(UserError::PROFILE_NOT_FOUND);
    }

    public static function userNotInWorkspace(): self
    {
        return static::make(UserError::USER_NOT_IN_WORKSPACE);
    }
}
