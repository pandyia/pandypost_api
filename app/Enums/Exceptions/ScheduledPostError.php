<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum ScheduledPostError: string implements ErrorEnumInterface
{
    case NO_ACCOUNT_LINKED = 'no_account_linked';
    case INVALID_ACCOUNT_SELECTION = 'invalid_account_selection';
    case PLATFORM_NOT_SUPPORTED = 'platform_not_supported';
    case CANCEL_NOT_ALLOWED = 'cancel_not_allowed';
    case RETRY_NOT_ALLOWED = 'retry_not_allowed';
    case MEDIA_EXPIRED = 'media_expired';
    case PERMALINK_UNAVAILABLE = 'permalink_unavailable';
    case SUBSCRIPTION_INACTIVE = 'subscription_inactive';
    case ACCOUNT_NOT_FOUND = 'account_not_found';

    public function message(?string $platform = null): string
    {
        return match ($this) {
            self::NO_ACCOUNT_LINKED => 'A conta selecionada não está disponível neste workspace.',
            self::INVALID_ACCOUNT_SELECTION => "A conta selecionada não pertence à plataforma {$platform} ou não está disponível para este usuário.",
            self::PLATFORM_NOT_SUPPORTED => "O serviço da plataforma '{$platform}' não suporta agendamentos diretos.",
            self::CANCEL_NOT_ALLOWED => 'Este post não pode ser cancelado pois já foi publicado ou está em processamento.',
            self::RETRY_NOT_ALLOWED => 'Só é possível reenviar posts que falharam.',
            self::MEDIA_EXPIRED => 'O arquivo deste post não está mais disponível. Adicione o post novamente.',
            self::PERMALINK_UNAVAILABLE => 'Não foi possível obter o link do post publicado no Instagram.',
            self::SUBSCRIPTION_INACTIVE => 'Assinatura inativa ou expirada no momento da publicação.',
            self::ACCOUNT_NOT_FOUND => 'A conta vinculada ao agendamento não foi encontrada. Reconecte a conta e tente novamente.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::NO_ACCOUNT_LINKED => 400,
            self::INVALID_ACCOUNT_SELECTION => 422,
            self::PLATFORM_NOT_SUPPORTED => 400,
            self::CANCEL_NOT_ALLOWED => 409,
            self::RETRY_NOT_ALLOWED => 409,
            self::MEDIA_EXPIRED => 422,
            self::PERMALINK_UNAVAILABLE => 502,
            self::SUBSCRIPTION_INACTIVE => 403,
            self::ACCOUNT_NOT_FOUND => 404,
        };
    }
}
