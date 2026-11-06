<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum YouTubeError: string implements ErrorEnumInterface
{
    // Erros nossos
    case REFRESH_TOKEN_MISSING = 'youtube_refresh_token_missing';
    case TOKEN_REFRESH_FAILED = 'youtube_token_refresh_failed';
    case UPLOAD_FAILED = 'youtube_upload_failed';

    // Motivos devolvidos pelo Google (`error.errors[].reason`)
    case QUOTA_EXCEEDED = 'quotaExceeded';
    case UPLOAD_LIMIT_EXCEEDED = 'uploadLimitExceeded';
    case ACCESS_DENIED = 'youtube_access_denied';
    case SIGNUP_REQUIRED = 'youtubeSignupRequired';
    case UNKNOWN = 'youtube_unknown_error';

    /** Motivo do Google -> erro nosso. Motivo ausente ou desconhecido vira UNKNOWN. */
    public static function fromReason(?string $reason): self
    {
        return match ($reason) {
            'quotaExceeded' => self::QUOTA_EXCEEDED,
            'uploadLimitExceeded' => self::UPLOAD_LIMIT_EXCEEDED,
            'forbidden', 'insufficientPermissions', 'authError', 'invalid_grant' => self::ACCESS_DENIED,
            'youtubeSignupRequired' => self::SIGNUP_REQUIRED,
            default => self::UNKNOWN,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::REFRESH_TOKEN_MISSING => 'Token expirado e sem refresh_token disponível.',
            self::TOKEN_REFRESH_FAILED => 'Não foi possível renovar o acesso à conta do YouTube. Reconecte a conta YouTube.',
            self::UPLOAD_FAILED => 'Falha ao fazer upload do vídeo para o YouTube.',
            self::QUOTA_EXCEEDED => 'A cota diária do YouTube foi esgotada. Tente novamente amanhã.',
            self::UPLOAD_LIMIT_EXCEEDED => 'O canal atingiu o limite de uploads do YouTube. Tente novamente mais tarde.',
            self::ACCESS_DENIED => 'O YouTube recusou o acesso à conta. Reconecte a conta YouTube e aceite todas as permissões.',
            self::SIGNUP_REQUIRED => 'A conta Google conectada não tem um canal do YouTube. Crie o canal e agende de novo.',
            self::UNKNOWN => 'O YouTube recusou a publicação. Tente novamente em instantes.',
        };
    }

    /** Erro que repetir não resolve (cota, permissão, canal, token): cada tentativa gasta cota do YouTube à toa. */
    public function isPermanent(): bool
    {
        return in_array($this, [
            self::REFRESH_TOKEN_MISSING,
            self::TOKEN_REFRESH_FAILED,
            self::QUOTA_EXCEEDED,
            self::UPLOAD_LIMIT_EXCEEDED,
            self::ACCESS_DENIED,
            self::SIGNUP_REQUIRED,
        ], true);
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::REFRESH_TOKEN_MISSING, self::TOKEN_REFRESH_FAILED, self::ACCESS_DENIED => 409,
            self::QUOTA_EXCEEDED, self::UPLOAD_LIMIT_EXCEEDED => 429,
            default => 502,
        };
    }
}
