<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum RoleError: string implements ErrorEnumInterface
{
    case ROLE_NAME_ALREADY_EXISTS = 'role_name_already_exists';
    case PROFILE_HAS_LINKED_USERS = 'profile_has_linked_users';

    public function message(): string
    {
        return match ($this) {
            self::ROLE_NAME_ALREADY_EXISTS => 'Já existe um perfil com esse nome neste workspace.',
            self::PROFILE_HAS_LINKED_USERS => 'Não é possível excluir um perfil com usuários vinculados.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::ROLE_NAME_ALREADY_EXISTS => 422,
            self::PROFILE_HAS_LINKED_USERS => 409,
        };
    }
}
