<?php

namespace App\Http\Controllers\Api\Billing\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Billing\Admin\StorePlanRequest;
use App\Http\Requests\Billing\Admin\UpdatePlanRequest;
use App\Http\Resources\Billing\Admin\PlanResource;
use App\Models\Plan;
use App\Services\Billing\Admin\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Admin: planos e preços
 *
 * Catálogo global de planos e preços, **só para o super admin** (dono do SaaS). Tudo é sincronizado com o Stripe.
 * Para qualquer outro usuário a resposta é 403.
 */
class PlanController extends BaseController
{
    protected ?string $resourceClass = PlanResource::class;

    protected static string $permissionGroup = 'billing';

    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
        'manage' => ['store', 'update', 'destroy'],
    ];

    public function __construct(
        private readonly PlanService $service
    ) {
        parent::__construct($service);
    }

    /**
     * Ver plano
     *
     * Com todos os preços.
     *
     * @urlParam plan string required UUID do plano. Example: 1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d
     *
     * @response 200 {"data": {
     *     "id": "1a2b3c4d-...",
     *     "name": "Pro",
     *     "description": "Para criadores que postam toda semana",
     *     "is_visible": true,
     *     "is_active": true,
     *     "gateway_product_id": "prod_Q1w2e3",
     *     "created_at": "2026-10-01T10:00:00.000000Z",
     *     "updated_at": "2026-10-01T10:00:00.000000Z",
     *     "prices": [{"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly", "trial_period_days": 7, "is_active": true, "created_at": "2026-10-01T10:00:00.000000Z", "updated_at": "2026-10-01T10:00:00.000000Z"}]
     *   }}
     */
    public function show(int|string $id): JsonResponse
    {
        $plan = $this->resolvePlan($id)->load('prices');

        return (new PlanResource($plan))->response();
    }

    /**
     * Criar plano
     *
     * Cria o produto no Stripe. Os preços são cadastrados depois.
     *
     * @bodyParam name string required Nome (máx. 255). Example: Pro
     * @bodyParam description string Descrição (máx. 1000). Example: Para criadores que postam toda semana
     * @bodyParam is_visible boolean Aparece na vitrine pública. Example: true
     * @bodyParam is_active boolean Disponível para assinatura. Example: true
     *
     * @response 201 {"message": "Plano criado com sucesso.", "data": {
     *     "id": "1a2b3c4d-...",
     *     "name": "Pro",
     *     "description": "Para criadores que postam toda semana",
     *     "is_visible": true,
     *     "is_active": true,
     *     "gateway_product_id": "prod_Q1w2e3",
     *     "created_at": "2026-10-01T10:00:00.000000Z",
     *     "updated_at": "2026-10-01T10:00:00.000000Z",
     *     "prices": []
     *   }}
     * @response 502 scenario="falha no Stripe" {"error": "gateway_error", "message": "Falha ao comunicar com o gateway de pagamento. Tente novamente."}
     * @response 403 scenario="não é super admin" {"message": "Você não tem permissão para acessar este recurso."}
     */
    public function store(Request $request): JsonResponse
    {
        $data = app(StorePlanRequest::class)->validated();

        $plan = $this->service->createPlan($data);

        return response()->json([
            'message' => 'Plano criado com sucesso.',
            'data' => new PlanResource($plan->load('prices')),
        ], 201);
    }

    /**
     * Editar plano
     *
     * Envie só o que mudar.
     *
     * @urlParam plan string required UUID do plano. Example: 1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d
     *
     * @bodyParam name string Nome (máx. 255). Example: Pro+
     * @bodyParam description string Descrição (máx. 1000). Example: Nova descrição
     * @bodyParam is_visible boolean Aparece na vitrine pública. Example: false
     * @bodyParam is_active boolean Disponível para assinatura. Example: true
     *
     * @response 200 {"message": "Plano atualizado com sucesso.", "data": {
     *     "id": "1a2b3c4d-...",
     *     "name": "Pro",
     *     "description": "Para criadores que postam toda semana",
     *     "is_visible": true,
     *     "is_active": true,
     *     "gateway_product_id": "prod_Q1w2e3",
     *     "created_at": "2026-10-01T10:00:00.000000Z",
     *     "updated_at": "2026-10-01T10:00:00.000000Z",
     *     "prices": [{"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly", "trial_period_days": 7, "is_active": true, "created_at": "2026-10-01T10:00:00.000000Z", "updated_at": "2026-10-01T10:00:00.000000Z"}]
     *   }}
     * @response 409 scenario="desativar plano com assinantes" {"error": "plan_has_active_subscriptions", "message": "Não é possível concluir a operação: o plano possui assinaturas ativas."}
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $data = app(UpdatePlanRequest::class)->validated();
        $plan = $this->resolvePlan($id);

        $plan = $this->service->updatePlan($plan, $data);

        return response()->json([
            'message' => 'Plano atualizado com sucesso.',
            'data' => new PlanResource($plan->load('prices')),
        ]);
    }

    /**
     * Remover plano
     *
     * Plano que já teve assinaturas é só desativado (some da vitrine, mas fica no histórico).
     *
     * @urlParam plan string required UUID do plano. Example: 1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d
     *
     * @response 200 {"message": "Plano removido com sucesso."}
     * @response 409 scenario="assinaturas ativas" {"error": "plan_has_active_subscriptions", "message": "Não é possível concluir a operação: o plano possui assinaturas ativas."}
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->service->deletePlan($this->resolvePlan($id));

        return response()->json(['message' => 'Plano removido com sucesso.']);
    }

    private function resolvePlan(int|string $id): Plan
    {
        $param = request()->route('plan');

        if ($param instanceof Plan) {
            return $param;
        }

        return Plan::where('uuid', $param)->firstOrFail();
    }

    /**
     * Listar planos
     *
     * Todos os planos, inclusive inativos e ocultos (sem `prices`).
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam name string Filtra por nome (parcial). Example: pro
     * @queryParam is_active boolean Filtra por ativo. Example: 1
     * @queryParam is_visible boolean Filtra por visível na vitrine. Example: 1
     *
     * @response 200 {
     *   "data": [{
     *     "id": "1a2b3c4d-...",
     *     "name": "Pro",
     *     "description": "Para criadores que postam toda semana",
     *     "is_visible": true,
     *     "is_active": true,
     *     "gateway_product_id": "prod_Q1w2e3",
     *     "created_at": "2026-10-01T10:00:00.000000Z",
     *     "updated_at": "2026-10-01T10:00:00.000000Z"
     *   }],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     * @response 403 scenario="não é super admin" {"message": "Você não tem permissão para acessar este recurso."}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }
}
