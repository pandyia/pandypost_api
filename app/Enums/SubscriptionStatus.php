<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativa',
            self::PAST_DUE => 'Vencida',
            self::CANCELED => 'Cancelada',
        };
    }
}
