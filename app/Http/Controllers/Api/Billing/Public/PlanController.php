<?php

namespace App\Http\Controllers\Api\Billing\Public;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Billing\Public\PlanCollection;
use App\Services\Billing\Public\PlanService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanController extends BaseController
{
    // Endpoint público: a landing page consome os planos ativos sem autenticação.
    // Sem $permissionGroup, o BaseController não registra o middleware de permissão.

    public function __construct(
        private PlanService $service
    ) {
        parent::__construct($service);
    }

    public function index(Request $request): Response
    {
        $plans = $this->service->getActivePlans();

        return (new PlanCollection($plans))->response();
    }
}