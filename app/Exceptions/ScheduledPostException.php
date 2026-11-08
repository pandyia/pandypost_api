<?php

namespace App\Exceptions;

use App\Enums\Exceptions\ScheduledPostError;

class ScheduledPostException extends BaseException
{
    public static function noAccountLinked(): self
    {
        return static::make(ScheduledPostError::NO_ACCOUNT_LINKED);
    }

    public static function platformNotSupported(string $platform): self
    {
        $error = ScheduledPostError::PLATFORM_NOT_SUPPORTED;

        return static::make($error, $error->message($platform));
    }

    public static function invalidAccountSelection(string $platform): self
    {
        $error = ScheduledPostError::INVALID_ACCOUNT_SELECTION;

        return static::make($error, $error->message($platform));
    }

    public static function cannotCancel(): self
    {
        return static::make(ScheduledPostError::CANCEL_NOT_ALLOWED);
    }

    public static function cannotRetry(): self
    {
        return static::make(ScheduledPostError::RETRY_NOT_ALLOWED);
    }

    public static function mediaExpired(): self
    {
        return static::make(ScheduledPostError::MEDIA_EXPIRED);
    }

    public static function permalinkUnavailable(): self
    {
        return static::make(ScheduledPostError::PERMALINK_UNAVAILABLE);
    }

    public static function subscriptionInactive(): self
    {
        return static::make(ScheduledPostError::SUBSCRIPTION_INACTIVE);
    }

    public static function accountNotFound(): self
    {
        return static::make(ScheduledPostError::ACCOUNT_NOT_FOUND);
    }

    public static function stuckTimeout(): self
    {
        return static::make(ScheduledPostError::STUCK_TIMEOUT);
    }

    public static function unverifiable(): self
    {
        return static::make(ScheduledPostError::UNVERIFIABLE);
    }

    public static function alreadyPublished(): self
    {
        return static::make(ScheduledPostError::ALREADY_PUBLISHED);
    }

    public static function stillProcessing(): self
    {
        return static::make(ScheduledPostError::STILL_PROCESSING);
    }

    public static function uploadExpired(): self
    {
        return static::make(ScheduledPostError::UPLOAD_EXPIRED);
    }

    public static function uploadNotFound(): self
    {
        return static::make(ScheduledPostError::UPLOAD_NOT_FOUND);
    }

    public static function uploadDeadlinePassed(): self
    {
        return static::make(ScheduledPostError::UPLOAD_DEADLINE_PASSED);
    }

    public static function uploadCancelled(): self
    {
        return static::make(ScheduledPostError::UPLOAD_CANCELLED);
    }
}
