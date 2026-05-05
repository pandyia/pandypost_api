<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\YouTubeAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AnalyticsController extends Controller
{
    private YouTubeAnalyticsService $analyticsService;

    public function __construct(YouTubeAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get dashboard analytics for a given social account.
     */
    public function dashboard(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        // Validação do Platform
        if ($socialAccount->platform !== 'youtube') {
            return response()->json(['message' => 'Analytics only supported for YouTube accounts currently'], 400);
        }

        $dateRange = $request->query('date_range', 'last_7_days');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $forceRefresh = $request->boolean('refresh');

        if ($forceRefresh) {
            // Limitar a atualização manual a 1 vez a cada 10 minutos (600 segundos) por conta
            $rateLimitKey = 'refresh_analytics_' . $socialAccount->id . '_' . $request->user()->id;
            
            if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                $minutes = ceil($seconds / 60);
                return response()->json([
                    'message' => "Você já atualizou os dados recentemente. Tente novamente em {$minutes} minutos."
                ], 429);
            }
            
            RateLimiter::hit($rateLimitKey, 600);
        }

        try {
            $data = $this->analyticsService->getDashboardData($socialAccount, $dateRange, $startDate, $endDate, $forceRefresh);
            return response()->json($data);
        } catch (Exception $e) {
            Log::error("Failed to fetch analytics for SocialAccount {$socialAccount->id}: " . $e->getMessage());

            // Check if it's a 401/403 or Token/Scope issue from Google
            if (stripos($e->getMessage(), 'Insufficient permission') !== false || str_contains($e->getMessage(), '403') || str_contains($e->getMessage(), '401')) {
                return response()->json([
                    'message' => 'Permissão insuficiente. Por favor, reautentique seu canal concedendo permissão de leitura do YouTube Analytics.',
                    'requires_reauth' => true
                ], 401);
            }

            return response()->json([
                'message' => 'Falha ao carregar métricas do YouTube. Tente novamente mais tarde.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the best times to post based on historical performance.
     */
    public function bestTimes(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        // Validação do Platform
        if ($socialAccount->platform !== 'youtube') {
            return response()->json(['best_hours' => [14, 18, 20]]); // Fallback padrão
        }

        try {
            $bestHours = $this->analyticsService->getBestPublishHours($socialAccount);
            return response()->json(['best_hours' => $bestHours]);
        } catch (Exception $e) {
            Log::error("Failed to fetch best publish hours for SocialAccount {$socialAccount->id}: " . $e->getMessage());
            return response()->json(['best_hours' => [14, 18, 20]]);
        }
    }
}
