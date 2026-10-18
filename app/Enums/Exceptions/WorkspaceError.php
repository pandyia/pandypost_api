<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum WorkspaceError: string implements ErrorEnumInterface
{
    case WORKSPACE_CANNOT_DELETED = 'workspace_cannot_deleted';
    case USER_NOT_LINKED_TO_WORKSPACE = 'user_not_linked_to_workspace';
    case WORKSPACE_NAME_ALREADY_EXISTS = 'workspace_name_already_exists';
    case INVALID_AVATAR_FILE = 'workspace_invalid_avatar_file';
    case AVATAR_TOO_LARGE = 'workspace_avatar_too_large';

    public function message(?string $context = null): string
    {
        return match ($this) {
            self::WORKSPACE_CANNOT_DELETED => 'Workspace pessoal não pode ser excluído.',
            self::USER_NOT_LINKED_TO_WORKSPACE => 'Você não está vinculado a este workspace.',
            self::WORKSPACE_NAME_ALREADY_EXISTS => 'Já existe um workspace com esse nome.',
            self::INVALID_AVATAR_FILE => 'O arquivo enviado não é uma imagem válida. Use JPG, PNG ou WebP.',
            self::AVATAR_TOO_LARGE => "A foto é maior que o limite de {$context} MB. Escolha uma imagem menor.",
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::WORKSPACE_CANNOT_DELETED => 400,
            self::USER_NOT_LINKED_TO_WORKSPACE => 403,
            self::WORKSPACE_NAME_ALREADY_EXISTS, self::INVALID_AVATAR_FILE, self::AVATAR_TOO_LARGE => 422,
        };
    }
}
