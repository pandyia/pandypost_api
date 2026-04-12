<?php

namespace App\Http\Controllers\Api;

use App\Enums\YouTubePrivacyStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class YouTubePrivacyStatusController extends Controller
{
    public function index(): JsonResponse
    {
        $statuses = collect(YouTubePrivacyStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        return response()->json($statuses);
    }
}
