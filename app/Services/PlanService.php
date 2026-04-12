<?php

namespace App\Services;

use App\Models\Plan;

class PlanService extends BaseService
{
    public function __construct(Plan $plan)
    {
        parent::__construct($plan);
    }

    public function getActivePlans()
    {
        return Plan::active()->get();
    }
}