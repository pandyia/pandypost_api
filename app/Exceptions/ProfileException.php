<?php

namespace App\Exceptions;

use App\Enums\Exceptions\ProfileError;

class ProfileException extends BaseException
{
    public static function invalidAvatarFile(): self
    {
        $error = ProfileError::INVALID_AVATAR_FILE;

        return static::make($error, $error->message());
    }

    public static function avatarTooLarge(int $maxBytes): self
    {
        $error = ProfileError::AVATAR_TOO_LARGE;

        return static::make($error, $error->message((string) round($maxBytes / 1048576)));
    }
}
