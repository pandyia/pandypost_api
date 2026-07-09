<?php

namespace App\Exceptions;

use App\Enums\Exceptions\BillingError;

class BillingException extends BaseException
{
    private static function make(BillingError $error): self
    {
        return new self($error->message(), $error, $error->httpCode());
    }

    public static function planHasActiveSubscriptions(): self
    {
        return self::make(BillingError::PLAN_HAS_ACTIVE_SUBSCRIPTIONS);
    }

    public static function planHasSubscriptions(): self
    {
        return self::make(BillingError::PLAN_HAS_SUBSCRIPTIONS);
    }

    public static function pricePlanMismatch(): self
    {
        return self::make(BillingError::PRICE_PLAN_MISMATCH);
    }

    public static function priceLastActiveInUse(): self
    {
        return self::make(BillingError::PRICE_LAST_ACTIVE_IN_USE);
    }

    public static function priceInUse(): self
    {
        return self::make(BillingError::PRICE_IN_USE);
    }

    public static function gatewayError(?string $detail = null): self
    {
        $exception = self::make(BillingError::GATEWAY_ERROR);

        return $detail ? $exception->withContext(['detail' => $detail]) : $exception;
    }

    public static function alreadySubscribed(): self
    {
        return self::make(BillingError::ALREADY_SUBSCRIBED);
    }

    public static function noActiveSubscription(): self
    {
        return self::make(BillingError::NO_ACTIVE_SUBSCRIPTION);
    }

    public static function defaultCardRequired(): self
    {
        return self::make(BillingError::DEFAULT_CARD_REQUIRED);
    }
}
