<?php

namespace App\Enums;

enum NotificationType: string
{
    case EMAIL_VERIFICATION = 'email_verification';
    case PASSWORD_RESET = 'password_reset';
    case WORKSPACE_INVITE = 'workspace_invite';
    case POST_FAILED = 'post_failed';
    case PAYMENT_FAILED = 'payment_failed';
    case SUBSCRIPTION_CANCELED = 'subscription_canceled';
    case SOCIAL_ACCOUNT_DISCONNECTED = 'social_account_disconnected';
    case POST_PUBLISHED = 'post_published';
    case WORKSPACE_INVITE_ACCEPTED = 'workspace_invite_accepted';
    case WORKSPACE_INVITE_DECLINED = 'workspace_invite_declined';

    /** Famílias cujo e-mail o usuário pode ligar/desligar. */
    public const PREFERENCE_FAMILIES = ['invite', 'posts', 'social_accounts', 'post_published'];

    /** Famílias cujo aviso no app vem desligado por padrão (opt-in). As demais vêm ligadas. */
    public const IN_APP_OFF_BY_DEFAULT = ['post_published'];

    /**
     * Família de preferência. `billing` é fixa (sempre e-mail); transacionais não têm família.
     */
    public function family(): ?string
    {
        return match ($this) {
            self::WORKSPACE_INVITE, self::WORKSPACE_INVITE_ACCEPTED, self::WORKSPACE_INVITE_DECLINED => 'invite',
            self::POST_FAILED => 'posts',
            self::SOCIAL_ACCOUNT_DISCONNECTED => 'social_accounts',
            self::POST_PUBLISHED => 'post_published',
            self::PAYMENT_FAILED, self::SUBSCRIPTION_CANCELED => 'billing',
            default => null,
        };
    }

    /** Tipos que aparecem na central (exclui os transacionais só de e-mail). */
    public function isCentral(): bool
    {
        return $this->family() !== null;
    }
}
