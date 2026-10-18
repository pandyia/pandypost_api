<?php

namespace App\Exceptions;

use App\Enums\Exceptions\WorkspaceError;

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

    public static function invalidAvatarFile(): self
    {
        return static::make(WorkspaceError::INVALID_AVATAR_FILE);
    }

    public static function avatarTooLarge(int $maxBytes): self
    {
        $error = WorkspaceError::AVATAR_TOO_LARGE;

        return static::make($error, $error->message((string) round($maxBytes / 1048576)));
    }

    public static function nameAlreadyExists(): self
    {
        return static::make(WorkspaceError::WORKSPACE_NAME_ALREADY_EXISTS);
    }
}
