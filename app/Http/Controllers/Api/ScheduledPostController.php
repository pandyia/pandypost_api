<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateUploadUrlRequest;
use App\Http\Requests\StoreScheduledPostRequest;
use App\Http\Resources\ScheduledPostResource;
use App\Services\ScheduledPostService;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledPostController extends BaseController
{
    protected ?string $resourceClass = ScheduledPostResource::class;

    protected static string $permissionGroup = 'posts';
    protected static array $permissionMethods = [
        'view'   => ['index'],
        'create' => ['store', 'uploadUrl'],
    ];

    public function __construct(
        private ScheduledPostService $service,
        private StorageService $storageService,
    ) {
        parent::__construct($service);
    }

    /**
     * Gera uma presigned PUT URL para o client fazer upload direto no S3.
     */
    public function uploadUrl(GenerateUploadUrlRequest $request): JsonResponse
    {
        $user   = $request->user();
        $access = $user->resolveCurrentAccess();

        if (!$access || !$access->workspace) {
            return response()->json(['message' => 'Workspace não encontrado.'], 404);
        }

        $result = $this->storageService->generateUploadUrl(
            directory: $request->input('directory'),
            workspaceUuid: $access->workspace->uuid,
            contentType: $request->input('content_type'),
            extension: $request->extension(),
        );

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $formRequest = app(StoreScheduledPostRequest::class);

        $posts = $this->service->schedule(
            $formRequest->user(),
            $formRequest->validated(),
        );

        $posts->each->load('socialAccount');

        return response()->json([
            'message' => 'Vídeo(s) agendado(s) com sucesso!',
            'data'    => ScheduledPostResource::collection($posts),
        ], 201);
    }
}