<?php

namespace App\Exceptions;
use App\Enums\Exceptions\WorkspaceError;

use Exception;

class WorkspaceException extends BaseException
{

    public static function personalTeamCannotDeleted(): self
    {
        $error = WorkspaceError::WORKSPACE_CANNOT_DELETED;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function userNotLinked(): self
    {
        $error = WorkspaceError::USER_NOT_LINKED_TO_WORKSPACE;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function nameAlreadyExists(): self
    {
        $error = WorkspaceError::WORKSPACE_NAME_ALREADY_EXISTS;
        return new self($error->message(), $error, $error->httpCode());
    }
}
