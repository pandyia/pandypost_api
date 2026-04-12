<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum WorkspaceError: string implements ErrorEnumInterface
{
    case WORKSPACE_CANNOT_DELETED = 'workspace_cannot_deleted';
    case USER_NOT_LINKED_TO_WORKSPACE = 'user_not_linked_to_workspace';
    case WORKSPACE_NAME_ALREADY_EXISTS = 'workspace_name_already_exists';

    public function message(): string
    {
        return match ($this) {
            self::WORKSPACE_CANNOT_DELETED => 'Workspace pessoal não pode ser excluído.',
            self::USER_NOT_LINKED_TO_WORKSPACE => 'Você não está vinculado a este workspace.',
            self::WORKSPACE_NAME_ALREADY_EXISTS => 'Já existe um workspace com esse nome.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::WORKSPACE_CANNOT_DELETED => 400,
            self::USER_NOT_LINKED_TO_WORKSPACE => 403,
            self::WORKSPACE_NAME_ALREADY_EXISTS => 422,
        };
    }
}
