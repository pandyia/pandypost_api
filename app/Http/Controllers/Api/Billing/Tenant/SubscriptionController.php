<?php

namespace App\Http\Controllers\Api\Billing\Tenant;

use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Tenant\ConfirmCheckoutRequest;
use App\Http\Requests\Billing\Tenant\StoreCheckoutRequest;
use App\Http\Resources\Billing\Tenant\SubscriptionResource;
use App\Models\Price;
use App\Models\Workspace;
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
        private readonly ConfirmCheckoutService $confirmCheckout
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
     *     "plan": {"id": "1a2b3c4d-...", "name": "Pro"},
     *     "price": {"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly"}
     *   }
     * }
     *
     * @responseField billing_status string Use este para a interface: `trialing` (em teste), `active` (em dia), `past_due` (pagamento pendente, acesso mantido) ou `blocked` (sem acesso).
     * @responseField ends_at string|null Preenchido quando a assinatura foi cancelada e termina nessa data.
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
    public function checkout(Request $request): JsonResponse
    {
        $data = app(StoreCheckoutRequest::class)->validated();
        $price = Price::where('uuid', $data['price'])->firstOrFail();

        $url = $this->checkout->start($this->workspace($request), $price);

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
    public function confirm(Request $request): JsonResponse
    {
        $data = app(ConfirmCheckoutRequest::class)->validated();

        $subscription = $this->confirmCheckout->confirm($this->workspace($request), $data['session_id']);

        // O JsonResource responderia 201 na primeira chamada (model recém-criado);
        // o contrato é 200 sempre, já que a confirmação é idempotente.
        return (new SubscriptionResource($subscription))->response()->setStatusCode(200);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->user()->resolveCurrentAccess()->workspace;
    }
}
