<?php

namespace App\Services\Billing\Public;

use App\Models\Plan;
use App\Services\BaseService;

class PlanService extends BaseService
{
    public function __construct(Plan $plan)
    {
        parent::__construct($plan);
    }

    /**
     * Planos visíveis e ativos com seus preços ativos, para a landing page.
     */
    public function getActivePlans()
    {
        return Plan::active()
            ->visible()
            ->with(['prices' => fn ($query) => $query->active()->orderBy('amount')])
            ->get();
    }
}