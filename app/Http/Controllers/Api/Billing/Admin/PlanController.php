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

    public function show(int|string $id): JsonResponse
    {
        $plan = $this->resolvePlan($id)->load('prices');

        return (new PlanResource($plan))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = app(StorePlanRequest::class)->validated();

        $plan = $this->service->createPlan($data);

        return response()->json([
            'message' => 'Plano criado com sucesso.',
            'data' => new PlanResource($plan->load('prices')),
        ], 201);
    }

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
}
