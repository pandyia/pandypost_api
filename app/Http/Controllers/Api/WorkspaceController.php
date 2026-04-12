<?php

namespace App\Http\Controllers\Api;

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
    public function update(Request $request, $uuid): JsonResponse
    {
        $this->workspaceService->updateByUuid($request->all(), $uuid);

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
