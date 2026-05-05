<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum PipelineError: string implements ErrorEnumInterface
{
    case INVALID_STAGE_TRANSITION = 'invalid_stage_transition';
    case SCHEDULED_STAGE_FORBIDDEN = 'scheduled_stage_forbidden';
    case CARD_NOT_FOUND = 'card_not_found';

    public function message(?string $context = null): string
    {
        return match ($this) {
            self::INVALID_STAGE_TRANSITION  => "Transição de etapa inválida" . ($context ? ": {$context}." : '.'),
            self::SCHEDULED_STAGE_FORBIDDEN => "A etapa 'scheduled' não pode ser definida manualmente. Ela é atribuída automaticamente ao agendar um post.",
            self::CARD_NOT_FOUND            => "Card do pipeline não encontrado.",
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::INVALID_STAGE_TRANSITION  => 422,
            self::SCHEDULED_STAGE_FORBIDDEN => 422,
            self::CARD_NOT_FOUND            => 404,
        };
    }
}
