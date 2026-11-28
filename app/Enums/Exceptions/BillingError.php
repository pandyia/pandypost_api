<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum BillingError: string implements ErrorEnumInterface
{
    case PLAN_HAS_ACTIVE_SUBSCRIPTIONS = 'plan_has_active_subscriptions';
    case PLAN_HAS_SUBSCRIPTIONS = 'plan_has_subscriptions';
    case PRICE_PLAN_MISMATCH = 'price_plan_mismatch';
    case PRICE_LAST_ACTIVE_IN_USE = 'price_last_active_in_use';
    case PRICE_IN_USE = 'price_in_use';
    case GATEWAY_ERROR = 'gateway_error';
    case ALREADY_SUBSCRIBED = 'already_subscribed';
    case NO_ACTIVE_SUBSCRIPTION = 'no_active_subscription';
    case DEFAULT_CARD_REQUIRED = 'default_card_required';
    case PRICE_NOT_SYNCED = 'price_not_synced';
    case CHECKOUT_SESSION_NOT_FOUND = 'checkout_session_not_found';
    case SUBSCRIPTION_CHANGE_NOT_ALLOWED = 'subscription_change_not_allowed';
    case SAME_PRICE = 'same_price';
    case PRICE_CURRENCY_MISMATCH = 'price_currency_mismatch';
    case PAYMENT_FAILED = 'payment_failed';
    case PAYMENT_ACTION_REQUIRED = 'payment_action_required';
    case NO_PENDING_CHANGE = 'no_pending_change';

    public function message(): string
    {
        return match ($this) {
            self::PLAN_HAS_ACTIVE_SUBSCRIPTIONS => 'Não é possível concluir a operação: o plano possui assinaturas ativas.',
            self::PLAN_HAS_SUBSCRIPTIONS => 'O plano possui (ou já possuiu) assinaturas e não pode ser removido; ele foi apenas desativado.',
            self::PRICE_PLAN_MISMATCH => 'O preço informado não pertence a este plano.',
            self::PRICE_LAST_ACTIVE_IN_USE => 'Este é o único preço ativo para a combinação de moeda e frequência; cadastre outro antes de removê-lo.',
            self::PRICE_IN_USE => 'O preço já foi utilizado em uma assinatura e não pode ser removido; ele foi apenas desativado.',
            self::GATEWAY_ERROR => 'Falha ao comunicar com o gateway de pagamento. Tente novamente.',
            self::ALREADY_SUBSCRIBED => 'Este workspace já possui uma assinatura ativa.',
            self::NO_ACTIVE_SUBSCRIPTION => 'Nenhuma assinatura ativa para este workspace.',
            self::DEFAULT_CARD_REQUIRED => 'Não é possível remover o cartão padrão; defina outro como padrão antes.',
            self::PRICE_NOT_SYNCED => 'Este preço ainda não foi sincronizado com o gateway de pagamento.',
            self::CHECKOUT_SESSION_NOT_FOUND => 'Sessão de checkout não encontrada para este workspace.',
            self::SUBSCRIPTION_CHANGE_NOT_ALLOWED => 'Não é possível trocar o plano agora: regularize o pagamento ou retome a assinatura antes.',
            self::SAME_PRICE => 'Este já é o preço atual da assinatura.',
            self::PRICE_CURRENCY_MISMATCH => 'O preço escolhido está em outra moeda e não pode substituir o atual.',
            self::PAYMENT_FAILED => 'Não foi possível cobrar a diferença no cartão. A assinatura continua no plano atual.',
            self::PAYMENT_ACTION_REQUIRED => 'O banco exige uma autorização para concluir a troca de plano. Autorize o pagamento e tente novamente.',
            self::NO_PENDING_CHANGE => 'Não há mudança de plano agendada para este workspace.',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::PLAN_HAS_ACTIVE_SUBSCRIPTIONS,
            self::PLAN_HAS_SUBSCRIPTIONS,
            self::PRICE_LAST_ACTIVE_IN_USE,
            self::PRICE_IN_USE,
            self::ALREADY_SUBSCRIBED,
            self::SUBSCRIPTION_CHANGE_NOT_ALLOWED => 409,
            self::PRICE_PLAN_MISMATCH,
            self::NO_ACTIVE_SUBSCRIPTION,
            self::CHECKOUT_SESSION_NOT_FOUND,
            self::NO_PENDING_CHANGE => 404,
            self::DEFAULT_CARD_REQUIRED,
            self::PRICE_NOT_SYNCED,
            self::SAME_PRICE,
            self::PRICE_CURRENCY_MISMATCH => 422,
            self::PAYMENT_FAILED,
            self::PAYMENT_ACTION_REQUIRED => 402,
            self::GATEWAY_ERROR => 502,
        };
    }
}
