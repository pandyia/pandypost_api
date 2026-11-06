<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum TikTokError: string implements ErrorEnumInterface
{
    // Erros nossos (validação antes de chamar a API)
    case SCOPE_MISSING = 'tiktok_scope_missing';
    case PRIVACY_NOT_ALLOWED = 'tiktok_privacy_not_allowed';
    case INVALID_RESPONSE = 'tiktok_invalid_response';
    case UPLOAD_FAILED = 'tiktok_upload_failed';
    case PUBLISH_TIMEOUT = 'tiktok_publish_timeout';
    case API_UNAVAILABLE = 'tiktok_api_unavailable';

    // Códigos devolvidos pelo TikTok (error.code / fail_reason)
    case SPAM_RISK_TOO_MANY_POSTS = 'spam_risk_too_many_posts';
    case REACHED_ACTIVE_USER_CAP = 'reached_active_user_cap';
    case UNAUDITED_CLIENT = 'unaudited_client_can_only_post_to_private_accounts';
    case SCOPE_NOT_AUTHORIZED = 'scope_not_authorized';
    case ACCESS_TOKEN_INVALID = 'access_token_invalid';
    case URL_OWNERSHIP_UNVERIFIED = 'url_ownership_unverified';
    case FILE_FORMAT_CHECK_FAILED = 'file_format_check_failed';
    case DURATION_CHECK_FAILED = 'duration_check_failed';
    case UNKNOWN = 'tiktok_unknown_error';

    /** Código do TikTok -> erro nosso. `fail_reason` `auth_removed` equivale a token inválido. */
    public static function fromApiCode(?string $code): self
    {
        return match ($code) {
            'auth_removed' => self::ACCESS_TOKEN_INVALID,
            null, '' => self::UNKNOWN,
            default => self::tryFrom($code) ?? self::UNKNOWN,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::SCOPE_MISSING => 'A conta do TikTok não tem permissão para publicar. Desconecte e reconecte a conta TikTok.',
            self::PRIVACY_NOT_ALLOWED => 'A privacidade escolhida não está disponível para esta conta do TikTok. Escolha outra e agende de novo.',
            self::INVALID_RESPONSE => 'O TikTok respondeu de forma inesperada. Tente novamente em instantes.',
            self::UPLOAD_FAILED => 'Não foi possível enviar o vídeo ao TikTok. Tente novamente em instantes.',
            self::PUBLISH_TIMEOUT => 'Tempo esgotado: o TikTok não confirmou a publicação do vídeo. Verifique a conta e tente novamente.',
            self::API_UNAVAILABLE => 'Não foi possível se comunicar com o TikTok. Tente novamente em instantes.',
            self::SPAM_RISK_TOO_MANY_POSTS => 'A conta atingiu o limite de publicações do TikTok nas últimas 24 horas. Tente novamente mais tarde.',
            self::REACHED_ACTIVE_USER_CAP => 'O aplicativo atingiu o limite diário de usuários ativos do TikTok. Tente novamente mais tarde.',
            self::UNAUDITED_CLIENT => 'App do TikTok não auditado: só é possível publicar em uma conta do TikTok privada, com visibilidade "somente eu". Deixe a conta privada no app do TikTok (Configurações e privacidade > Privacidade > Conta privada) e agende de novo.',
            self::SCOPE_NOT_AUTHORIZED => 'A conta do TikTok não autorizou a publicação de vídeos. Reconecte a conta TikTok e aceite todas as permissões.',
            self::ACCESS_TOKEN_INVALID => 'O acesso à conta do TikTok expirou ou foi revogado. Reconecte a conta TikTok.',
            self::URL_OWNERSHIP_UNVERIFIED => 'O domínio do vídeo não foi verificado no TikTok. Fale com o suporte.',
            self::FILE_FORMAT_CHECK_FAILED => 'O TikTok não aceitou o formato do arquivo. Use um vídeo MP4, MOV ou WebM.',
            self::DURATION_CHECK_FAILED => 'O vídeo é mais longo do que o TikTok permite para esta conta.',
            self::UNKNOWN => 'O TikTok recusou a publicação. Tente novamente em instantes.',
        };
    }

    /** Erro que repetir não resolve (token, permissão, privacidade indisponível): os jobs falham na hora em vez de tentar de novo. */
    public function isPermanent(): bool
    {
        return in_array($this, [self::SCOPE_MISSING, self::SCOPE_NOT_AUTHORIZED, self::ACCESS_TOKEN_INVALID, self::PRIVACY_NOT_ALLOWED, self::UNAUDITED_CLIENT], true);
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::SCOPE_MISSING, self::SCOPE_NOT_AUTHORIZED, self::ACCESS_TOKEN_INVALID => 409,
            self::PRIVACY_NOT_ALLOWED => 422,
            self::SPAM_RISK_TOO_MANY_POSTS => 429,
            default => 502,
        };
    }
}
