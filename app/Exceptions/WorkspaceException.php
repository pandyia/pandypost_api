<?php

namespace App\Exceptions;
use App\Enums\Exceptions\WorkspaceError;

use Exception;

class WorkspaceException extends BaseException
{
    public static function personalTeamCannotDeleted(): self
    {
        return static::make(WorkspaceError::WORKSPACE_CANNOT_DELETED);
    }

    public static function userNotLinked(): self
    {
        return static::make(WorkspaceError::USER_NOT_LINKED_TO_WORKSPACE);
    }

    public static function nameAlreadyExists(): self
    {
        return static::make(WorkspaceError::WORKSPACE_NAME_ALREADY_EXISTS);
    }
}
