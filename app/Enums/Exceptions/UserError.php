<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum UserError: string implements ErrorEnumInterface
{
    case CANNOT_REMOVE_YOURSELF = 'cannot_remove_yourself';
    case WORKSPACE_MUST_HAVE_AT_LEAST_ONE_USER = 'workspace_must_have_at_least_one_user';
    case PROFILE_NOT_FOUND = 'profile_not_found';
    case USER_NOT_IN_WORKSPACE = 'user_not_in_workspace';

    public function message(): string
    {
        return match ($this) {
            self::CANNOT_REMOVE_YOURSELF => 'Você não pode remover a si mesmo do workspace.',
            self::WORKSPACE_MUST_HAVE_AT_LEAST_ONE_USER => 'O workspace deve ter pelo menos um usuário.',
            self::PROFILE_NOT_FOUND => 'Perfil não encontrado no workspace.',
            self::USER_NOT_IN_WORKSPACE => 'O usuário não pertence ao workspace atual.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::CANNOT_REMOVE_YOURSELF => 400,
            self::WORKSPACE_MUST_HAVE_AT_LEAST_ONE_USER => 400,
            self::PROFILE_NOT_FOUND => 404,
            self::USER_NOT_IN_WORKSPACE => 403,
        };
    }
}
