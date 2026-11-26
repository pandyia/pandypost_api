<?php

namespace App\Exceptions;

use App\Enums\Exceptions\WorkspaceError;
use Illuminate\Http\JsonResponse;

class WorkspaceException extends BaseException
{
    /** Preenchida só pela recusa de permissão, que tem corpo de resposta próprio (ver render). */
    private ?string $requiredPermission = null;

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

    public static function permissionDeniedInWorkspace(string $permission): self
    {
        $exception = static::make(WorkspaceError::PERMISSION_DENIED_IN_WORKSPACE);
        $exception->requiredPermission = $permission;

        return $exception;
    }

    /**
     * A recusa de permissão mantém o corpo idêntico ao do middleware CheckPermission
     * ({"message", "required_permission"}, 403) — aprovado na Q3 da spec 022.
     */
    public function render(): JsonResponse
    {
        if ($this->requiredPermission === null) {
            return parent::render();
        }

        return response()->json([
            'message' => $this->getMessage(),
            'required_permission' => $this->requiredPermission,
        ], $this->getHttpCode());
    }
}
