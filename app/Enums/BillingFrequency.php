<?php

namespace App\Enums;

enum BillingFrequency: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    /**
     * Mapeia a frequência para o `recurring.interval` do Stripe.
     */
    public function stripeInterval(): string
    {
        return match ($this) {
            self::MONTHLY => 'month',
            self::YEARLY => 'year',
        };
    }
}
