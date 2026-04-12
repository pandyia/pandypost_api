<?php

namespace App\Exceptions;

use App\Enums\Exceptions\InviteError;

class InviteException extends BaseException
{
    public static function alreadyInvited(): self
    {
        $error = InviteError::ALREADY_INVITED;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function notYourInvite(): self
    {
        $error = InviteError::NOT_YOUR_INVITE;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function inviteNotPending(): self
    {
        $error = InviteError::INVITE_NOT_PENDING;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function alreadyMember(): self
    {
        $error = InviteError::ALREADY_MEMBER;
        return new self($error->message(), $error, $error->httpCode());
    }
}
