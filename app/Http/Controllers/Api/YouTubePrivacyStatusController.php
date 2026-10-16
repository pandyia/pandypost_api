<?php

namespace App\Http\Controllers\Api;

use App\Enums\YouTubePrivacyStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @group Agendamentos
 */
class YouTubePrivacyStatusController extends Controller
{
    /**
     * Privacidades do YouTube
     *
     * Opções para `youtube_privacy_status`.
     *
     * @response 200 [
     *   {"value": "public", "label": "Público"},
     *   {"value": "private", "label": "Privado"},
     *   {"value": "unlisted", "label": "Não listado"}
     * ]
     */
    public function index(): JsonResponse
    {
        $statuses = collect(YouTubePrivacyStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        return response()->json($statuses);
    }
}
