<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum SubscriptionError: string implements ErrorEnumInterface
{
    case PLAN_NOT_FOUND = 'plan_not_found';
    case SUBSCRIPTION_INACTIVE = 'subscription_inactive';
    case QUOTA_EXCEEDED = 'quota_exceeded';

    public function message(): string
    {
        return match ($this) {
            self::PLAN_NOT_FOUND => 'Plano não encontrado.',
            self::SUBSCRIPTION_INACTIVE => 'Sua assinatura está inativa.',
            self::QUOTA_EXCEEDED => 'Você atingiu o limite de posts do seu plano.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::PLAN_NOT_FOUND => 404,
            self::SUBSCRIPTION_INACTIVE => 402,
            self::QUOTA_EXCEEDED => 402,
        };
    }
}
