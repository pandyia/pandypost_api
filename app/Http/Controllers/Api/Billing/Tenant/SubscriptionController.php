<?php

namespace App\Http\Controllers\Api\Billing\Tenant;

use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Tenant\ChangeSubscriptionRequest;
use App\Http\Requests\Billing\Tenant\ConfirmCheckoutRequest;
use App\Http\Requests\Billing\Tenant\StoreCheckoutRequest;
use App\Http\Resources\Billing\Tenant\SubscriptionResource;
use App\Models\Workspace;
use App\Services\Billing\Tenant\ChangeSubscriptionService;
use App\Services\Billing\Tenant\CheckoutService;
use App\Services\Billing\Tenant\ConfirmCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Assinatura e pagamentos
 *
 * Assinatura, cartões e faturas **do workspace corrente** (a cobrança é por workspace, via Stripe).
 *
 * **Fluxo de assinatura:** `POST /billing/subscription/checkout` → redirecione para `checkout_url` (página do Stripe)
 * → o Stripe volta para o frontend → consulte `GET /billing/subscription`.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly ConfirmCheckoutService $confirmCheckout,
        private readonly ChangeSubscriptionService $changeSubscription
    ) {}

    /**
     * Assinatura atual
     *
     * **Permissão:** `billing.view`
     *
     * @response 200 {
     *   "data": {
     *     "billing_status": "active",
     *     "stripe_status": "active",
     *     "on_trial": false,
     *     "trial_ends_at": null,
     *     "ends_at": null,
     *     "next_billing_at": "2027-01-15T12:00:00-03:00",
     *     "plan": {"id": "1a2b3c4d-...", "name": "Pro"},
     *     "price": {"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly"},
     *     "pending_change": {
     *       "price": {"id": "3c4d5e6f-1111-4222-8333-444455556666", "amount": 2990, "currency": "brl", "frequency": "monthly"},
     *       "plan": {"id": "7a8b9c0d-1111-4222-8333-444455556666", "name": "Básico"},
     *       "effective_at": "2026-12-26T14:00:00.000000Z"
     *     }
     *   }
     * }
     *
     * @responseField pending_change object|null Troca de plano agendada (downgrade ou anual→mensal), aplicada em `effective_at` (fim do ciclo atual). `null` quando não há agendamento. Enquanto existir, `plan`/`price` continuam sendo o plano atual. Veja `POST /billing/subscription/change`.
     * @responseField billing_status string Use este para a interface: `trialing` (em teste), `active` (em dia), `past_due` (pagamento pendente, acesso mantido) ou `blocked` (sem acesso).
     * @responseField ends_at string|null Preenchido quando a assinatura foi cancelada e termina nessa data.
     * @responseField next_billing_at string|null Data da próxima cobrança: o fim do teste grátis, se estiver em teste; senão o fim do período atual. `null` se a assinatura está encerrando ou se não foi possível consultar o Stripe (não é erro: não mostre a data).
     *
     * @response 404 scenario="sem assinatura" {"error": "no_active_subscription", "message": "Nenhuma assinatura ativa para este workspace."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.view"}
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = $this->workspace($request)->subscription('default');

        if (! $subscription) {
            throw BillingException::noActiveSubscription();
        }

        return (new SubscriptionResource($subscription))->response();
    }

    /**
     * Iniciar checkout
     *
     * Cria a sessão do Stripe Checkout. Redirecione o usuário para `checkout_url`.
     *
     * **Permissão:** `billing.manage`
     *
     * @bodyParam price string required `id` de um preço ativo (de `GET /plans`). Example: 9f8e7d6c-5b4a-4321-8fed-cba987654321
     *
     * @response 200 {"checkout_url": "https://checkout.stripe.com/c/pay/cs_test_..."}
     * @response 409 scenario="já assinante" {"error": "already_subscribed", "message": "Este workspace já possui uma assinatura ativa."}
     * @response 422 scenario="preço não sincronizado" {"error": "price_not_synced", "message": "Este preço ainda não foi sincronizado com o gateway de pagamento."}
     * @response 502 scenario="falha no Stripe" {"error": "gateway_error", "message": "Falha ao comunicar com o gateway de pagamento. Tente novamente."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.manage"}
     */
    public function checkout(StoreCheckoutRequest $request): JsonResponse
    {
        $url = $this->checkout->start($this->workspace($request), $request->validated()['price']);

        return response()->json(['checkout_url' => $url]);
    }

    /**
     * Confirmar checkout
     *
     * Chame ao voltar do Stripe, com o `session_id` recebido na URL de sucesso. A API confirma a sessão direto no Stripe
     * e cria a assinatura sem esperar o webhook. É idempotente.
     *
     * **Permissão:** `billing.manage`
     *
     * @bodyParam session_id string required `session_id` devolvido pelo Stripe na URL de sucesso. Example: cs_test_a1b2c3
     *
     * @response 200 {"data": {"billing_status": "active", "stripe_status": "active", "on_trial": false, "trial_ends_at": null, "ends_at": null, "plan": {"id": "1a2b3c4d-...", "name": "Pro"}, "price": {"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly"}}}
     * @response 404 scenario="sessão de outro workspace" {"error": "checkout_session_not_found", "message": "Sessão de checkout não encontrada para este workspace."}
     * @response 404 scenario="checkout não concluído" {"error": "no_active_subscription", "message": "Nenhuma assinatura ativa para este workspace."}
     * @response 502 scenario="falha no Stripe" {"error": "gateway_error", "message": "Falha ao comunicar com o gateway de pagamento. Tente novamente."}
     */
    public function confirm(ConfirmCheckoutRequest $request): JsonResponse
    {
        $subscription = $this->confirmCheckout->confirm($this->workspace($request), $request->validated()['session_id']);

        // O JsonResource responderia 201 na primeira chamada (model recém-criado);
        // o contrato é 200 sempre, já que a confirmação é idempotente.
        return (new SubscriptionResource($subscription))->response()->setStatusCode(200);
    }

    /**
     * Trocar de plano ou frequência
     *
     * Muda o preço (plano e/ou frequência) da assinatura atual. A resposta diz se a troca já valeu ou ficou agendada:
     *
     * - **Imediata** (`pending_change` = `null` na resposta; `message` "Plano alterado com sucesso."): upgrade (mesma
     *   frequência e valor maior), mensal→anual e **qualquer troca durante o trial**. O Stripe cobra a diferença
     *   pro-rata na hora, no cartão padrão. Se a assinatura estava em trial, o trial **termina** e a cobrança começa agora.
     * - **Agendada** (`pending_change` preenchido; `message` "Mudança de plano agendada para o fim do ciclo atual."):
     *   downgrade (mesma frequência e valor menor) e anual→mensal. Nada é cobrado nem muda agora; o plano atual vale
     *   até `pending_change.effective_at`. Uma nova troca substitui o agendamento anterior; repetir o mesmo downgrade
     *   já agendado devolve 200 sem alterar nada. Para desfazer, use `DELETE /billing/subscription/change`.
     *
     * Uma troca imediata cancela qualquer agendamento existente. Só assinaturas `active` ou `trialing`, sem cancelamento
     * pedido, podem trocar. Os avisos de pagamento (402) deixam a assinatura no plano antigo: não há troca parcial.
     * Os usuários com permissão de billing recebem a notificação `subscription_changed`.
     *
     * **Permissão:** `billing.manage`
     *
     * @bodyParam price string required `id` (uuid) de um preço ativo de um plano ativo (de `GET /plans`). Example: 9f8e7d6c-5b4a-4321-8fed-cba987654321
     *
     * @response 200 scenario="troca imediata (upgrade)" {"message": "Plano alterado com sucesso.", "data": {"billing_status": "active", "stripe_status": "active", "on_trial": false, "trial_ends_at": null, "ends_at": null, "plan": {"id": "1a2b3c4d-...", "name": "Pro"}, "price": {"id": "9f8e7d6c-5b4a-4321-8fed-cba987654321", "amount": 4990, "currency": "brl", "frequency": "monthly"}, "pending_change": null}}
     * @response 200 scenario="troca agendada (downgrade)" {"message": "Mudança de plano agendada para o fim do ciclo atual.", "data": {"billing_status": "active", "stripe_status": "active", "on_trial": false, "trial_ends_at": null, "ends_at": null, "plan": {"id": "1a2b3c4d-...", "name": "Pro"}, "price": {"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly"}, "pending_change": {"price": {"id": "3c4d5e6f-1111-4222-8333-444455556666", "amount": 2990, "currency": "brl", "frequency": "monthly"}, "plan": {"id": "7a8b9c0d-1111-4222-8333-444455556666", "name": "Básico"}, "effective_at": "2026-12-26T14:00:00.000000Z"}}}
     * @response 402 scenario="cartão recusado" {"error": "payment_failed", "message": "Não foi possível cobrar a diferença no cartão. A assinatura continua no plano atual."}
     * @response 402 scenario="banco exige autorização (3DS)" {"error": "payment_action_required", "message": "O banco exige uma autorização para concluir a troca de plano. Autorize o pagamento e tente novamente.", "action_url": "https://invoice.stripe.com/i/acct_1Abc/test_YWNjdF8x"}
     * @response 402 scenario="banco exige autorização, sem link" {"error": "payment_action_required", "message": "O banco exige uma autorização para concluir a troca de plano. Autorize o pagamento e tente novamente."}
     * @response 404 scenario="sem assinatura" {"error": "no_active_subscription", "message": "Nenhuma assinatura ativa para este workspace."}
     * @response 404 scenario="preço inexistente, inativo ou de plano inativo" {"message": "No query results for model [App\\Models\\Price]."}
     * @response 409 scenario="assinatura não pode trocar agora" {"error": "subscription_change_not_allowed", "message": "Não é possível trocar o plano agora: regularize o pagamento ou retome a assinatura antes."}
     * @response 422 scenario="mesmo preço da assinatura" {"error": "same_price", "message": "Este já é o preço atual da assinatura."}
     * @response 422 scenario="moeda diferente" {"error": "price_currency_mismatch", "message": "O preço escolhido está em outra moeda e não pode substituir o atual."}
     * @response 422 scenario="preço não sincronizado" {"error": "price_not_synced", "message": "Este preço ainda não foi sincronizado com o gateway de pagamento."}
     * @response 422 scenario="price ausente ou não é uuid" {"message": "O preço é obrigatório.", "errors": {"price": ["O preço é obrigatório."]}}
     * @response 502 scenario="falha no Stripe" {"error": "gateway_error", "message": "Falha ao comunicar com o gateway de pagamento. Tente novamente."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.manage"}
     *
     * @responseField action_url string (só no 402 `payment_action_required`, na **raiz** do JSON, não dentro de `data`) Link da fatura hospedada do Stripe para o usuário autorizar o pagamento. **Pode estar ausente**: se faltar, mostre só a `message` e peça para tentar de novo ou revisar o cartão.
     * @responseField pending_change object|null Preenchido quando a troca ficou agendada. Veja `GET /billing/subscription`.
     */
    public function change(ChangeSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->changeSubscription->change($this->workspace($request), $request->validated()['price']);

        return response()->json([
            'message' => $subscription->pendingChange
                ? 'Mudança de plano agendada para o fim do ciclo atual.'
                : 'Plano alterado com sucesso.',
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * Desfazer mudança agendada
     *
     * Cancela o downgrade / anual→mensal agendado e mantém o plano atual. Devolve a assinatura com `pending_change: null`.
     *
     * **Permissão:** `billing.manage`
     *
     * @response 200 {"message": "Mudança de plano agendada cancelada.", "data": {"billing_status": "active", "stripe_status": "active", "on_trial": false, "trial_ends_at": null, "ends_at": null, "plan": {"id": "1a2b3c4d-...", "name": "Pro"}, "price": {"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly"}, "pending_change": null}}
     * @response 404 scenario="nada agendado (ou sem assinatura)" {"error": "no_pending_change", "message": "Não há mudança de plano agendada para este workspace."}
     * @response 502 scenario="falha no Stripe" {"error": "gateway_error", "message": "Falha ao comunicar com o gateway de pagamento. Tente novamente."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.manage"}
     */
    public function cancelChange(Request $request): JsonResponse
    {
        $subscription = $this->changeSubscription->cancelPending($this->workspace($request));

        return response()->json([
            'message' => 'Mudança de plano agendada cancelada.',
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->user()->resolveCurrentAccess()->workspace;
    }
}
