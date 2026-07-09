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
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::PLAN_HAS_ACTIVE_SUBSCRIPTIONS,
            self::PLAN_HAS_SUBSCRIPTIONS,
            self::PRICE_LAST_ACTIVE_IN_USE,
            self::PRICE_IN_USE,
            self::ALREADY_SUBSCRIBED => 409,
            self::PRICE_PLAN_MISMATCH,
            self::NO_ACTIVE_SUBSCRIPTION => 404,
            self::DEFAULT_CARD_REQUIRED => 422,
            self::GATEWAY_ERROR => 502,
        };
    }
}
