<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduledPostRequest;
use App\Http\Resources\ScheduledPostResource;
use App\Services\ScheduledPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledPostController extends BaseController
{
    protected ?string $resourceClass = ScheduledPostResource::class;

    protected static string $permissionGroup = 'posts';
    protected static array $permissionMethods = [
        'view'   => ['index'],
        'create' => ['store'],
    ];

    public function __construct(
        private ScheduledPostService $service
    ) {
        parent::__construct($service);
    }

    public function store(Request $request): JsonResponse
    {
        $formRequest = app(StoreScheduledPostRequest::class);

        $post = $this->service->schedule(
            $formRequest->user(),
            $formRequest->validated(),
            $formRequest->file('video'),
            $formRequest->file('thumbnail')
        );

        $post->load('socialAccount');

        return response()->json([
            'message' => 'Vídeo agendado com sucesso!',
            'data' => new ScheduledPostResource($post)
        ], 201);
    }
}