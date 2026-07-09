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

    public function index(Request $request): Response
    {
        return PriceResource::collection($this->service->forPlan($this->resolvePlan()))->response();
    }

    public function versions(Request $request): AnonymousResourceCollection
    {
        $price = $this->resolvePrice($this->resolvePlan());

        return PriceHistoryResource::collection($this->service->versions($price));
    }

    public function store(Request $request): JsonResponse
    {
        $data = app(StorePriceRequest::class)->validated();

        $price = $this->service->createPrice($this->resolvePlan(), $data);

        return response()->json([
            'message' => 'Preço adicionado com sucesso.',
            'data' => new PriceResource($price),
        ], 201);
    }

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
