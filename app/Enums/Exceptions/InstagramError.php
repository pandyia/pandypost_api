<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum InstagramError: string implements ErrorEnumInterface
{
    // Erros nossos
    case API_UNAVAILABLE = 'instagram_api_unavailable';
    case INVALID_RESPONSE = 'instagram_invalid_response';
    case CONTAINER_FAILED = 'instagram_container_failed';
    case PUBLISH_TIMEOUT = 'instagram_publish_timeout';

    // Devolvidos pela Graph API (error.code)
    case ACCESS_DENIED = 'instagram_access_denied';
    case UNKNOWN = 'instagram_unknown_error';

    /**
     * Códigos de token inválido/permissão ausente, os mesmos que o InstagramAnalyticsProvider trata como
     * reautenticação (10, 102, 190 e a faixa 200-299). Os demais caem em UNKNOWN: não há código confirmado
     * para outros erros e a mensagem crua da Meta nunca chega ao usuário.
     */
    public static function fromApiCode(?int $code): self
    {
        $isAccessDenied = in_array($code, [10, 102, 190], true) || ($code >= 200 && $code <= 299);

        return $isAccessDenied ? self::ACCESS_DENIED : self::UNKNOWN;
    }

    public function message(): string
    {
        return match ($this) {
            self::API_UNAVAILABLE => 'Não foi possível se comunicar com o Instagram. Tente novamente em instantes.',
            self::INVALID_RESPONSE => 'O Instagram respondeu de forma inesperada. Tente novamente em instantes.',
            self::CONTAINER_FAILED => 'O Instagram não conseguiu processar a mídia. Verifique o formato do arquivo e tente novamente.',
            self::PUBLISH_TIMEOUT => 'Tempo esgotado: o Instagram não terminou de processar a mídia. Verifique a conta e tente novamente.',
            self::ACCESS_DENIED => 'O acesso à conta do Instagram expirou, foi revogado ou não tem permissão para publicar. Reconecte a conta do Instagram.',
            self::UNKNOWN => 'O Instagram recusou a publicação. Verifique a mídia e tente novamente em instantes.',
        };
    }

    /** Erro que repetir não resolve (token/permissão): o job de status falha na hora em vez de tentar de novo. */
    public function isPermanent(): bool
    {
        return $this === self::ACCESS_DENIED;
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::ACCESS_DENIED => 409,
            default => 502,
        };
    }
}
