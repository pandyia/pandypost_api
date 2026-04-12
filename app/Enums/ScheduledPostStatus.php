<?php

namespace App\Enums;

enum ScheduledPostStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::PUBLISHED => 'Publicado',
            self::FAILED => 'Falhou',
            self::CANCELLED => 'Cancelado',
        };
    }
}
