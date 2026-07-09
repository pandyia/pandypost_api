<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case VOID = 'void';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::PAID => 'Pago',
            self::FAILED => 'Falhou',
            self::EXPIRED => 'Expirado',
            self::VOID => 'Cancelado',
        };
    }
}
