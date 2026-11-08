<?php

namespace App\Enums;

enum ScheduledPostStatus: string
{
    case AWAITING_UPLOAD = 'awaiting_upload';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AWAITING_UPLOAD => 'Aguardando envio do vídeo',
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::PUBLISHED => 'Publicado',
            self::FAILED => 'Falhou',
            self::CANCELLED => 'Cancelado',
        };
    }
}
