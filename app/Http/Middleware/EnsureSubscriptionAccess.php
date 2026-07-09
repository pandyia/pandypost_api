<?php

namespace App\Http\Middleware;

use App\Exceptions\SubscriptionException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforcement de acesso por inadimplência (dunning simplificado).
 *
 * Uma única checagem: a assinatura do workspace corrente é válida?
 * `valid()` do Cashier = active | trialing | onGracePeriod, e com
 * `keepPastDueSubscriptionsActive()` também cobre `past_due` (janela de
 * retentativas do Stripe). Se não for válida → 402.
 *
 * OBS: NÃO está aplicado às rotas ainda (decisão da fase: publish permanece
 * liberado). Será plugado na fase Cliente, quando o checkout existir.
 */
class EnsureSubscriptionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->user()?->resolveCurrentAccess()?->workspace;

        $subscription = $workspace?->subscription('default');

        if ($subscription && $subscription->valid()) {
            return $next($request);
        }

        throw SubscriptionException::subscriptionInactive();
    }
}
