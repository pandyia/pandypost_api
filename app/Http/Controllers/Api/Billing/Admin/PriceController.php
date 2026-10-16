<?php

namespace App\Http\Controllers\Api\Billing\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Billing\Admin\StorePriceRequest;
use App\Http\Requests\Billing\Admin\UpdatePriceRequest;
use App\Http\Resources\Billing\Admin\PriceHistoryResource;
use App\Http\Resources\Billing\Admin\PriceResource;
use App\Models\Plan;
use App\Models\Price;
use App\Services\Billing\Admin\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Admin: planos e preços
 */
class PriceController extends BaseController
{
    protected static string $permissionGroup = 'billing';

    protected static array $permissionMethods = [
        'view' => ['index', 'versions'],
        'manage' => ['store', 'update', 'destroy'],
    ];

    public function __construct(
        private readonly PriceService $service
    ) {
        parent::__construct($service);
    }

    /**
     * Listar preços do plano
     *
     * @urlParam plan string required UUID do plano. Example: 1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d
     *
     * @response 200 {"data": [{"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly", "trial_period_days": 7, "is_active": true, "created_at": "2026-10-01T10:00:00.000000Z", "updated_at": "2026-10-01T10:00:00.000000Z"}]}
     */
    public function index(Request $request): Response
    {
        return PriceResource::collection($this->service->forPlan($this->resolvePlan()))->response();
    }

    /**
     * Histórico do preço
     *
     * Versões anteriores, gravadas a cada edição, mais recentes primeiro.
     *
     * @urlParam plan string required UUID do plano. Example: 1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d
     * @urlParam price string required UUID do preço. Example: 9f8e7d6c-5b4a-4321-8fed-cba987654321
     *
     * @response 200 {"data": [{"id": "7c6b5a4d-...", "amount": 3990, "currency": "brl", "frequency": "monthly", "trial_period_days": 7, "reason": "Reajuste anual", "archived_at": "2026-10-01T10:00:00.000000Z", "created_at": "2026-10-01T10:00:00.000000Z"}]}
     */
    public function versions(Request $request): AnonymousResourceCollection
    {
        $price = $this->resolvePrice($this->resolvePlan());

        return PriceHistoryResource::collection($this->service->versions($price));
    }

    /**
     * Adicionar preço
     *
     * Cria o preço no Stripe.
     *
     * @urlParam plan string required UUID do plano. Example: 1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d
     *
     * @bodyParam amount integer required Valor em centavos. Example: 4990
     * @bodyParam currency string required Moeda. Enum: brl, usd, eur, gbp. Example: brl
     * @bodyParam frequency string required Recorrência. Enum: monthly, yearly. Example: monthly
     * @bodyParam trial_period_days integer Dias de teste grátis (0 a 365). Example: 7
     *
     * @response 201 {"message": "Preço adicionado com sucesso.", "data": {"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly", "trial_period_days": 7, "is_active": true, "created_at": "2026-10-01T10:00:00.000000Z", "updated_at": "2026-10-01T10:00:00.000000Z"}}
     * @response 502 scenario="falha no Stripe" {"error": "gateway_error", "message": "Falha ao comunicar com o gateway de pagamento. Tente novamente."}
     */
    public function store(Request $request): JsonResponse
    {
        $data = app(StorePriceRequest::class)->validated();

        $price = $this->service->createPrice($this->resolvePlan(), $data);

        return response()->json([
            'message' => 'Preço adicionado com sucesso.',
            'data' => new PriceResource($price),
        ], 201);
    }

    /**
     * Editar preço
     *
     * No Stripe preço não se edita: é criado um novo e o anterior vai para o histórico. Quem já assina segue no valor antigo.
     *
     * @urlParam plan string required UUID do plano. Example: 1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d
     * @urlParam price string required UUID do preço. Example: 9f8e7d6c-5b4a-4321-8fed-cba987654321
     *
     * @bodyParam amount integer Valor em centavos. Example: 5990
     * @bodyParam currency string Moeda. Enum: brl, usd, eur, gbp. Example: brl
     * @bodyParam frequency string Recorrência. Enum: monthly, yearly. Example: monthly
     * @bodyParam trial_period_days integer Dias de teste grátis (0 a 365). Example: 7
     * @bodyParam reason string Motivo da mudança, fica no histórico (máx. 255). Example: Reajuste anual
     *
     * @response 200 {"message": "Preço atualizado com sucesso.", "data": {"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly", "trial_period_days": 7, "is_active": true, "created_at": "2026-10-01T10:00:00.000000Z", "updated_at": "2026-10-01T10:00:00.000000Z"}}
     * @response 404 scenario="preço de outro plano" {"error": "price_plan_mismatch", "message": "O preço informado não pertence a este plano."}
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $data = app(UpdatePriceRequest::class)->validated();
        $plan = $this->resolvePlan();
        $price = $this->resolvePrice($plan);

        $price = $this->service->updatePrice($plan, $price, $data);

        return response()->json([
            'message' => 'Preço atualizado com sucesso.',
            'data' => new PriceResource($price),
        ]);
    }

    /**
     * Remover preço
     *
     * Preço já usado em assinatura é só desativado.
     *
     * @urlParam plan string required UUID do plano. Example: 1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d
     * @urlParam price string required UUID do preço. Example: 9f8e7d6c-5b4a-4321-8fed-cba987654321
     *
     * @response 200 {"message": "Preço removido com sucesso."}
     * @response 409 scenario="único preço ativo da combinação" {"error": "price_last_active_in_use", "message": "Este é o único preço ativo para a combinação de moeda e frequência; cadastre outro antes de removê-lo."}
     */
    public function destroy(int|string $id): JsonResponse
    {
        $plan = $this->resolvePlan();
        $this->service->deletePrice($plan, $this->resolvePrice($plan));

        return response()->json(['message' => 'Preço removido com sucesso.']);
    }

    private function resolvePlan(): Plan
    {
        $param = request()->route('plan');

        if ($param instanceof Plan) {
            return $param;
        }

        return Plan::where('uuid', $param)->firstOrFail();
    }

    private function resolvePrice(Plan $plan): Price
    {
        $param = request()->route('price');

        if ($param instanceof Price) {
            return $param;
        }

        return $plan->prices()->where('uuid', $param)->firstOrFail();
    }
}
