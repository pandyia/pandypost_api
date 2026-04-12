<?php

namespace App\Exceptions;

use App\Enums\Exceptions\UserError;

class UserException extends BaseException
{
    public static function cannotRemoveYourself(): self
    {
        $error = UserError::CANNOT_REMOVE_YOURSELF;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function workspaceMustHaveAtLeastOneUser(): self
    {
        $error = UserError::WORKSPACE_MUST_HAVE_AT_LEAST_ONE_USER;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function profileNotFound(): self
    {
        $error = UserError::PROFILE_NOT_FOUND;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function userNotInWorkspace(): self
    {
        $error = UserError::USER_NOT_IN_WORKSPACE;
        return new self($error->message(), $error, $error->httpCode());
    }
}
