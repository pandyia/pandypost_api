<?php

namespace App\Enums;

enum Currency: string
{
    case BRL = 'brl';
    case USD = 'usd';
    case EUR = 'eur';
    case GBP = 'gbp';

    public function symbol(): string
    {
        return match ($this) {
            self::BRL => 'R$',
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
        };
    }
}
