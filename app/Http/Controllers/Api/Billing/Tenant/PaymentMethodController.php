<?php

namespace App\Http\Controllers\Api\Billing\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Tenant\StorePaymentMethodRequest;
use App\Http\Resources\Billing\Tenant\PaymentMethodResource;
use App\Models\Workspace;
use App\Services\Billing\Tenant\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Assinatura e pagamentos
 */
class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodService $service
    ) {}

    /**
     * Preparar cadastro de cartão
     *
     * Devolve o `client_secret` para o Stripe.js. Fluxo: chame este endpoint → `stripe.confirmCardSetup(client_secret, ...)`
     * → envie o `payment_method` resultante para `POST /billing/cards`.
     *
     * **Permissão:** `billing.view`
     *
     * @response 200 {"client_secret": "seti_1Q2w3E_secret_abc"}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.view"}
     */
    public function setupIntent(Request $request): JsonResponse
    {
        return response()->json([
            'client_secret' => $this->service->setupIntentSecret($this->workspace($request)),
        ]);
    }

    /**
     * Listar cartões
     *
     * **Permissão:** `billing.view`
     *
     * @response 200 {
     *   "data": [
     *     {"id": "pm_1Q2w3E4r5T6y", "brand": "visa", "last_digits": "4242", "exp_month": 12, "exp_year": 2028, "is_default": true}
     *   ]
     * }
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.view"}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection($this->service->list($this->workspace($request)));
    }

    /**
     * Adicionar cartão
     *
     * **Permissão:** `billing.manage`
     *
     * @bodyParam payment_method string required ID do PaymentMethod criado pelo Stripe.js. Example: pm_1Q2w3E4r5T6y
     * @bodyParam is_default boolean Tornar o cartão padrão. Example: true
     *
     * @response 201 {"message": "Cartão adicionado com sucesso."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.manage"}
     */
    public function store(Request $request): JsonResponse
    {
        $data = app(StorePaymentMethodRequest::class)->validated();

        $this->service->add(
            $this->workspace($request),
            $data['payment_method'],
            (bool) ($data['is_default'] ?? false),
        );

        return response()->json(['message' => 'Cartão adicionado com sucesso.'], 201);
    }

    /**
     * Definir cartão padrão
     *
     * **Permissão:** `billing.manage`
     *
     * @urlParam paymentMethod string required ID do cartão no Stripe (`id` da listagem). Example: pm_1Q2w3E4r5T6y
     *
     * @response 200 {"message": "Cartão padrão atualizado com sucesso."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.manage"}
     */
    public function update(Request $request, string $paymentMethod): JsonResponse
    {
        $this->service->setDefault($this->workspace($request), $paymentMethod);

        return response()->json(['message' => 'Cartão padrão atualizado com sucesso.']);
    }

    /**
     * Remover cartão
     *
     * O cartão padrão não pode ser removido: defina outro como padrão antes.
     *
     * **Permissão:** `billing.manage`
     *
     * @urlParam paymentMethod string required ID do cartão no Stripe (`id` da listagem). Example: pm_1Q2w3E4r5T6y
     *
     * @response 200 {"message": "Cartão removido com sucesso."}
     * @response 422 scenario="cartão padrão" {"error": "default_card_required", "message": "Não é possível remover o cartão padrão; defina outro como padrão antes."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.manage"}
     */
    public function destroy(Request $request, string $paymentMethod): JsonResponse
    {
        $this->service->remove($this->workspace($request), $paymentMethod);

        return response()->json(['message' => 'Cartão removido com sucesso.']);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->user()->resolveCurrentAccess()->workspace;
    }
}
