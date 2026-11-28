<?php

namespace App\Exceptions;

use App\Enums\Exceptions\BillingError;
use Illuminate\Http\JsonResponse;

class BillingException extends BaseException
{
    protected ?string $actionUrl = null;

    public static function planHasActiveSubscriptions(): self
    {
        return static::make(BillingError::PLAN_HAS_ACTIVE_SUBSCRIPTIONS);
    }

    public static function planHasSubscriptions(): self
    {
        return static::make(BillingError::PLAN_HAS_SUBSCRIPTIONS);
    }

    public static function pricePlanMismatch(): self
    {
        return static::make(BillingError::PRICE_PLAN_MISMATCH);
    }

    public static function priceLastActiveInUse(): self
    {
        return static::make(BillingError::PRICE_LAST_ACTIVE_IN_USE);
    }

    public static function priceInUse(): self
    {
        return static::make(BillingError::PRICE_IN_USE);
    }

    public static function gatewayError(?string $detail = null): self
    {
        $exception = static::make(BillingError::GATEWAY_ERROR);

        return $detail ? $exception->withContext(['detail' => $detail]) : $exception;
    }

    public static function alreadySubscribed(): self
    {
        return static::make(BillingError::ALREADY_SUBSCRIBED);
    }

    public static function checkoutSessionNotFound(): self
    {
        return static::make(BillingError::CHECKOUT_SESSION_NOT_FOUND);
    }

    public static function noActiveSubscription(): self
    {
        return static::make(BillingError::NO_ACTIVE_SUBSCRIPTION);
    }

    public static function defaultCardRequired(): self
    {
        return static::make(BillingError::DEFAULT_CARD_REQUIRED);
    }

    public static function priceNotSynced(): self
    {
        return static::make(BillingError::PRICE_NOT_SYNCED);
    }

    public static function subscriptionChangeNotAllowed(): self
    {
        return static::make(BillingError::SUBSCRIPTION_CHANGE_NOT_ALLOWED);
    }

    public static function samePrice(): self
    {
        return static::make(BillingError::SAME_PRICE);
    }

    public static function priceCurrencyMismatch(): self
    {
        return static::make(BillingError::PRICE_CURRENCY_MISMATCH);
    }

    public static function paymentFailed(): self
    {
        return static::make(BillingError::PAYMENT_FAILED);
    }

    /** O `action_url` (fatura hospedada do Stripe) vai na raiz da resposta, para o cliente autorizar o 3DS. */
    public static function paymentActionRequired(?string $actionUrl = null): self
    {
        $exception = static::make(BillingError::PAYMENT_ACTION_REQUIRED);
        $exception->actionUrl = $actionUrl;

        return $exception;
    }

    public static function noPendingChange(): self
    {
        return static::make(BillingError::NO_PENDING_CHANGE);
    }

    public function render(): JsonResponse
    {
        $response = parent::render();

        if ($this->actionUrl) {
            $response->setData(array_merge((array) $response->getData(true), ['action_url' => $this->actionUrl]));
        }

        return $response;
    }
}
