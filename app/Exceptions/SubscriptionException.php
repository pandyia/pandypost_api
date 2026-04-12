<?php

namespace App\Exceptions;

use App\Enums\Exceptions\SubscriptionError;

class SubscriptionException extends BaseException
{
    public static function planNotFound(): self
    {
        $error = SubscriptionError::PLAN_NOT_FOUND;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function subscriptionInactive(): self
    {
        $error = SubscriptionError::SUBSCRIPTION_INACTIVE;
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function quotaExceeded(): self
    {
        $error = SubscriptionError::QUOTA_EXCEEDED;
        return new self($error->message(), $error, $error->httpCode());
    }


}
