<?php

namespace App\Exceptions;

use App\Enums\Exceptions\InviteError;

class InviteException extends BaseException
{
    public static function alreadyInvited(): self
    {
        return static::make(InviteError::ALREADY_INVITED);
    }

    public static function notYourInvite(): self
    {
        return static::make(InviteError::NOT_YOUR_INVITE);
    }

    public static function inviteNotPending(): self
    {
        return static::make(InviteError::INVITE_NOT_PENDING);
    }

    public static function alreadyMember(): self
    {
        return static::make(InviteError::ALREADY_MEMBER);
    }
}
