<?php

namespace App\Http\Controllers\Api\Billing\Tenant;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Billing\Tenant\PaymentResource;
use App\Services\Billing\Tenant\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends BaseController
{
    protected ?string $resourceClass = PaymentResource::class;

    protected static string $permissionGroup = 'billing';
    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
    ];

    public function __construct(
        private readonly PaymentService $service
    ) {
        parent::__construct($service);
    }

    public function show(int|string $id): JsonResponse
    {
        $payment = $this->service->findByUuid(request()->route('payment'));

        return (new PaymentResource($payment))->response();
    }
}
