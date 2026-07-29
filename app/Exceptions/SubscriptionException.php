<?php

namespace App\Exceptions;

use App\Enums\Exceptions\SubscriptionError;

class SubscriptionException extends BaseException
{
    public static function planNotFound(): self
    {
        return static::make(SubscriptionError::PLAN_NOT_FOUND);
    }

    public static function subscriptionInactive(): self
    {
        return static::make(SubscriptionError::SUBSCRIPTION_INACTIVE);
    }

    public static function quotaExceeded(): self
    {
        return static::make(SubscriptionError::QUOTA_EXCEEDED);
    }
}
