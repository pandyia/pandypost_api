<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Http\Resources\SwitchWorkspaceResource;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends BaseController
{
    protected static string $permissionGroup = 'workspaces';

    protected static array $permissionMethods = [
        'view' => ['index', 'show', 'switchWorkspace'],
        'create' => ['store'],
        'update' => ['update'],
        'delete' => ['destroy'],
    ];

    public function __construct(
        private WorkspaceService $workspaceService
    ) {
        parent::__construct($workspaceService);
    }

    public function store(Request $request): JsonResponse
    {
        $data = app(StoreWorkspaceRequest::class)->validated();

        $this->workspaceService->store($data);

        return response()->json(['message' => 'Registro inserido com sucesso.'], 201);
    }

    public function update(Request $request, $uuid): JsonResponse
    {
        $data = app(UpdateWorkspaceRequest::class)->validated();

        $this->workspaceService->updateByUuid($data, $uuid);

        return response()->json(['message' => 'Registro atualizado com sucesso.'], 200);
    }

    public function destroy($uuid): JsonResponse
    {
        $this->workspaceService->destroyByUuid($uuid);

        return response()->json(['message' => 'Registro deletado com sucesso.'], 204);
    }

    public function switchWorkspace(Request $request, string $uuid)
    {
        $access = $this->workspaceService->switchWorkspace($request->user(), $uuid);

        return new SwitchWorkspaceResource($access);
    }
}
