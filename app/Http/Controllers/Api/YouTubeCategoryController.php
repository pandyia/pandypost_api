<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\YouTubeCategory;
use Illuminate\Http\JsonResponse;

/**
 * @group Agendamentos
 */
class YouTubeCategoryController extends Controller
{
    /**
     * Categorias do YouTube
     *
     * Opções para `youtube_category_id`. Use `id` como valor e `name` como rótulo.
     *
     * @response 200 [
     *   {"id": 1, "name": "Film & Animation", "created_at": "2026-10-10T16:52:26.000000Z", "updated_at": "2026-10-10T16:52:26.000000Z"},
     *   {"id": 22, "name": "People & Blogs", "created_at": "2026-10-10T16:52:26.000000Z", "updated_at": "2026-10-10T16:52:26.000000Z"}
     * ]
     */
    public function index(): JsonResponse
    {
        return response()->json(YouTubeCategory::all());
    }
}
