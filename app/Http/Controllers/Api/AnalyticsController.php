<?php

namespace App\Http\Controllers\Api;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\YouTubeAnalyticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AnalyticsController extends Controller
{
    private const REFRESH_COOLDOWN_SECONDS = 600;

    public function __construct(
        private readonly YouTubeAnalyticsService $analyticsService,
    ) {}

    /**
     * GET /api/analytics/{socialAccount}/dashboard
     */
    public function dashboard(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        if ($socialAccount->platform !== Platform::YOUTUBE->value) {
            return response()->json(['message' => 'Analytics only supported for YouTube accounts currently'], 400);
        }

        $forceRefresh = $request->boolean('refresh');

        if ($forceRefresh && ($minutes = $this->refreshCooldownMinutes($request, $socialAccount))) {
            return response()->json([
                'message' => "Você já atualizou os dados recentemente. Tente novamente em {$minutes} minutos.",
            ], 429);
        }

        try {
            return response()->json($this->analyticsService->getDashboardData(
                $socialAccount,
                $request->query('date_range', 'last_7_days'),
                $request->query('start_date'),
                $request->query('end_date'),
                $forceRefresh,
            ));
        } catch (Exception $e) {
            Log::error("Failed to fetch analytics for SocialAccount {$socialAccount->id}: {$e->getMessage()}");

            return $this->dashboardErrorResponse($e);
        }
    }

    /**
     * GET /api/analytics/{socialAccount}/best-times
     */
    public function bestTimes(SocialAccount $socialAccount): JsonResponse
    {
        $bestHours = $socialAccount->platform === Platform::YOUTUBE->value
            ? $this->analyticsService->getBestPublishHours($socialAccount)
            : YouTubeAnalyticsService::DEFAULT_BEST_HOURS;

        return response()->json(['best_hours' => $bestHours]);
    }

    /**
     * Libera uma atualização manual a cada 10 min por conta e usuário. Devolve os minutos de espera, se houver.
     */
    private function refreshCooldownMinutes(Request $request, SocialAccount $account): ?int
    {
        $key = "refresh_analytics_{$account->id}_{$request->user()->id}";

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return (int) ceil(RateLimiter::availableIn($key) / 60);
        }

        RateLimiter::hit($key, self::REFRESH_COOLDOWN_SECONDS);

        return null;
    }

    private function dashboardErrorResponse(Exception $e): JsonResponse
    {
        $message = $e->getMessage();
        $requiresReauth = stripos($message, 'Insufficient permission') !== false
            || str_contains($message, '403')
            || str_contains($message, '401');

        if ($requiresReauth) {
            return response()->json([
                'message' => 'Permissão insuficiente. Por favor, reautentique seu canal concedendo permissão de leitura do YouTube Analytics.',
                'requires_reauth' => true,
            ], 401);
        }

        return response()->json([
            'message' => 'Falha ao carregar métricas do YouTube. Tente novamente mais tarde.',
            // Detalhe técnico só em desenvolvimento.
            ...(config('app.debug') ? ['error' => $message] : []),
        ], 500);
    }
}
