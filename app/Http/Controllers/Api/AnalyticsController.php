<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsDashboardRequest;
use App\Http\Resources\AnalyticsDashboardResource;
use App\Http\Resources\BestTimesResource;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    /**
     * Dashboard da conta social (YouTube, Instagram ou TikTok), no contrato normalizado.
     */
    public function dashboard(AnalyticsDashboardRequest $request, SocialAccount $socialAccount): JsonResponse
    {
        $data = $request->validated();
        $period = $this->analyticsService->resolvePeriod($socialAccount, $data);

        $dashboard = $this->analyticsService->dashboard(
            $socialAccount,
            $period,
            $request->user(),
            (bool) ($data['refresh'] ?? false),
        );

        return (new AnalyticsDashboardResource($dashboard))->response();
    }

    /**
     * Melhores horários para postar, pela média de visualizações por hora de publicação.
     */
    public function bestTimes(SocialAccount $socialAccount): JsonResponse
    {
        return (new BestTimesResource($this->analyticsService->bestTimes($socialAccount)))->response();
    }
}
