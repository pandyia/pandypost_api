<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Subscription as CashierSubscription;
use Throwable;

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

    /**
     * Datas que o Stripe/Cashier mandam como timestamp chegam em UTC (Carbon 3). Gravadas assim, o
     * banco guarda a hora UTC e a leitura a interpreta no fuso do app, adiantando a data em horas
     * (ex.: teste que já acabou seguia "em teste"). Converte para o fuso do app antes de gravar.
     */
    protected function trialEndsAt(): Attribute
    {
        return $this->inAppTimezone();
    }

    protected function endsAt(): Attribute
    {
        return $this->inAppTimezone();
    }

    private function inAppTimezone(): Attribute
    {
        return Attribute::set(fn ($value) => $value instanceof CarbonInterface
            ? $value->copy()->setTimezone(date_default_timezone_get())
            : $value);
    }

    /** Chave do cache da data da próxima cobrança (descartada quando o plano muda). */
    public function nextBillingCacheKey(): string
    {
        return "billing:next-billing:{$this->stripe_id}";
    }

    /**
     * Data da próxima cobrança: fim do teste, se estiver em teste; senão o fim do período atual no
     * Stripe (cache curto, pois o Cashier não guarda essa data). Sem cobrança futura (assinatura
     * encerrando), com o gateway desligado ou se o Stripe falhar, devolve null — nunca quebra a tela.
     */
    public function nextBillingAt(): ?Carbon
    {
        if ($this->ends_at) {
            return null;
        }

        if ($this->onTrial()) {
            return $this->trial_ends_at;
        }

        if (! config('services.stripe.enabled')) {
            return null;
        }

        $timestamp = Cache::remember($this->nextBillingCacheKey(), now()->addMinutes(5), function () {
            try {
                $remote = $this->asStripeSubscription();

                return $remote->current_period_end ?? $remote->items->data[0]->current_period_end ?? null;
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        });

        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp, date_default_timezone_get()) : null;
    }

    /** Mudança de plano agendada para o fim do ciclo (spec 024); no máximo uma `pending`. */
    public function pendingChange(): HasOne
    {
        return $this->hasOne(SubscriptionChange::class)->where('status', 'pending')->with('price.plan');
    }
}
