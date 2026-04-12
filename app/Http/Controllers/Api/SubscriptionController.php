<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends BaseController
{
    protected static string $permissionGroup = 'billing';
    protected static array $permissionMethods = [
        'manage' => ['subscribe'],
    ];

    public function __construct(
        private SubscriptionService $service
    ) {
        parent::__construct($service);
    }

    public function subscribe(StoreSubscriptionRequest $request): JsonResponse
    {
        $result = $this->service->subscribe(
            $request->user(),
            $request->plan_id
        );

        return response()->json([
            'message' => "Plano {$result['plan_name']} ativado com sucesso!",
            'limit' => $result['posts_limit']
        ]);
    }
}