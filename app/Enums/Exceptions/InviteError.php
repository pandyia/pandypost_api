<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum InviteError: string implements ErrorEnumInterface
{
    case ALREADY_INVITED = 'already_invited';
    case NOT_YOUR_INVITE = 'not_your_invite';
    case INVITE_NOT_PENDING = 'invite_not_pending';
    case ALREADY_MEMBER = 'already_member';

    public function message(): string
    {
        return match ($this) {
            self::ALREADY_INVITED => 'Usuário já foi convidado para este workspace.',
            self::NOT_YOUR_INVITE => 'Este convite pertence a outro usuário.',
            self::INVITE_NOT_PENDING => 'Este convite já foi processado ou expirou.',
            self::ALREADY_MEMBER => 'Este usuário já é membro deste workspace.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::ALREADY_INVITED => 409,
            self::NOT_YOUR_INVITE => 403,
            self::INVITE_NOT_PENDING => 400,
            self::ALREADY_MEMBER => 409,
        };
    }
}
