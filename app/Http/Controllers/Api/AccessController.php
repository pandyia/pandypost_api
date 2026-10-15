<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreAccessRequest;
use App\Services\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccessController extends BaseController
{
    protected static string $permissionGroup = 'accesses';

    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
        'create' => ['store'],
        'update' => ['update'],
        'delete' => ['destroy'],
    ];

    public function __construct(
        private AccessService $accessService
    ) {
        parent::__construct($accessService);
    }

    public function index(Request $request): Response
    {
        $data = $this->accessService->workspaceAccesses($request->input());

        return response($data, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $formRequest = app(StoreAccessRequest::class);
        $workspaceId = $request->user()->resolveCurrentAccess()->workspace_id;

        $this->accessService->store(
            array_merge($formRequest->validated(), ['workspace_id' => $workspaceId])
        );

        return response()->json(['message' => 'Registro inserido com sucesso.'], 201);
    }
}
