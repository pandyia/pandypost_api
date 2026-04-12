<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Services\PlanService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends BaseController
{
    protected static string $permissionGroup = 'billing';
    protected static array $permissionMethods = [
        'view' => ['index'],
    ];

    public function __construct(
        private PlanService $service
    ) {
        parent::__construct($service);
    }

    public function index(): AnonymousResourceCollection
    {
        $plans = $this->service->getActivePlans();

        return PlanResource::collection($plans);
    }
}