<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

/**
 * Subclasse do model de assinatura do Cashier (apontada em
 * Cashier::useSubscriptionModel), keyed pelo Workspace.
 *
 * Não há colunas custom de dunning (`access_level`/`past_due_since`): o estado
 * de acesso é DERIVADO do `stripe_status`. Esta subclasse apenas expõe um
 * `billing_status` simples para o front, sem persistir nada.
 */
class Subscription extends CashierSubscription
{
    /**
     * Estado de faturamento derivado, para exibição/decisão no front.
     *
     * - trialing: em período de teste
     * - past_due: pagamento pendente (janela de retentativas do Stripe) — acesso mantido
     * - active:   em dia
     * - blocked:  retentativas esgotadas / cancelada — sem acesso
     */
    public function getBillingStatusAttribute(): string
    {
        return match (true) {
            $this->onTrial() => 'trialing',
            $this->pastDue() => 'past_due',
            $this->valid() => 'active',
            default => 'blocked',
        };
    }
}
