<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum ScheduledPostError: string implements ErrorEnumInterface
{
    case NO_ACCOUNT_LINKED = 'no_account_linked';
    case INVALID_ACCOUNT_SELECTION = 'invalid_account_selection';
    case PLATFORM_NOT_SUPPORTED = 'platform_not_supported';
    case CANCEL_NOT_ALLOWED = 'cancel_not_allowed';

    public function message(?string $platform = null): string
    {
        return match ($this) {
            self::NO_ACCOUNT_LINKED => "Nenhuma conta do {$platform} vinculada.",
            self::INVALID_ACCOUNT_SELECTION => "A conta selecionada não pertence à plataforma {$platform} ou não está disponível para este usuário.",
            self::PLATFORM_NOT_SUPPORTED => "O serviço da plataforma '{$platform}' não suporta agendamentos diretos.",
            self::CANCEL_NOT_ALLOWED => 'Este post não pode ser cancelado pois já foi publicado ou está em processamento.'
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::NO_ACCOUNT_LINKED => 400,
            self::INVALID_ACCOUNT_SELECTION => 422,
            self::PLATFORM_NOT_SUPPORTED => 400,
            self::CANCEL_NOT_ALLOWED => 409,
        };
    }
}
