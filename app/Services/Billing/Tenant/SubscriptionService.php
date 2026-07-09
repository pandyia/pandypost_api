<?php

namespace App\Services\Billing\Tenant;

use App\Models\Subscription;
use App\Models\User;
use App\Services\BaseService;

/**
 * FASE 2: o modelo de assinatura migrou para o Workspace (Cashier) e os limites
 * de plano foram deferidos. Os métodos de gate/quota abaixo ficaram como no-op
 * para não bloquear a publicação durante a transição (decisão da fase).
 *
 * O enforcement de acesso por inadimplência será feito via
 * App\Http\Middleware\EnsureSubscriptionAccess na fase Cliente; a criação de
 * assinatura passará a ser via Stripe Checkout (Cashier), não mais por aqui.
 */
class SubscriptionService extends BaseService
{
    public function __construct(Subscription $subscription)
    {
        parent::__construct($subscription);
    }

    /**
     * DEFERIDO: sem bloqueio de publicação nesta fase.
     */
    public function ensureValidSubscription(User $user): void
    {
        // no-op — ver docblock da classe.
    }

    /**
     * DEFERIDO: limites/quota de plano fora de escopo nesta fase.
     */
    public function consumeQuota(User $user, int $amount): void
    {
        // no-op — ver docblock da classe.
    }
}
