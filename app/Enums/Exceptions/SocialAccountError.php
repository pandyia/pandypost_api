<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum SocialAccountError: string implements ErrorEnumInterface
{
    case AUTH_FAILED = 'auth_failed';
    case INVALID_CODE = 'invalid_code';
    case TOKEN_EXCHANGE_FAILED = 'token_exchange_failed';
    case PLATFORM_NOT_SUPPORTED = 'platform_not_supported';
    case ACCOUNT_ALREADY_LINKED = 'account_already_linked';
    case INVALID_OAUTH_STATE = 'invalid_oauth_state';
    case OAUTH_TOKEN_EXCHANGE_FAILED = 'oauth_token_exchange_failed';
    case OAUTH_INITIALIZATION_FAILED = 'oauth_initialization_failed';

    public function message(?string $platform = null): string
    {
        return match ($this) {
            self::AUTH_FAILED => "Falha na autenticação do {$platform}.",
            self::INVALID_CODE => 'Código de autorização inválido ou expirado.',
            self::TOKEN_EXCHANGE_FAILED => 'Falha ao obter token de acesso.',
            self::PLATFORM_NOT_SUPPORTED => "Plataforma '{$platform}' não é suportada.",
            self::ACCOUNT_ALREADY_LINKED => "Conta do {$platform} já está vinculada a outro usuário.",
            self::INVALID_OAUTH_STATE => 'State OAuth inválido ou expirado.',
            self::OAUTH_TOKEN_EXCHANGE_FAILED => 'Falha ao trocar o código pelo token de acesso.',
            self::OAUTH_INITIALIZATION_FAILED => 'Falha ao iniciar o fluxo OAuth.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::AUTH_FAILED => 401,
            self::INVALID_CODE => 400,
            self::TOKEN_EXCHANGE_FAILED => 502,
            self::PLATFORM_NOT_SUPPORTED => 400,
            self::ACCOUNT_ALREADY_LINKED => 409,
            self::INVALID_OAUTH_STATE => 400,
            self::OAUTH_TOKEN_EXCHANGE_FAILED => 400,
            self::OAUTH_INITIALIZATION_FAILED => 500,
        };
    }
}
