<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\YouTubeCategory;
use Illuminate\Http\JsonResponse;

class YouTubeCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(YouTubeCategory::all());
    }
}
