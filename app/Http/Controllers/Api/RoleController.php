<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends BaseController
{
    protected ?string $resourceClass = RoleResource::class;

    protected static string $permissionGroup = 'roles';
    protected static array $permissionMethods = [
        'view'   => ['index', 'show'],
        'create' => ['store'],
        'update' => ['update'],
        'delete' => ['destroy'],
    ];

    public function __construct(
        private RoleService $roleService
    ) {
        parent::__construct($roleService);
    }

    public function show(int|string $id): Response
    {
        $role = $this->roleService->findByUuid($id)->load(['permissions', 'users']);

        return (new RoleResource($role))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $role = $this->roleService->createRole($request->all());

        return response()->json([
            'message' => 'Perfil criado com sucesso',
            'data' => new RoleResource($role),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $updated = $this->roleService->updateRole($request->all(), $id);

        return response()->json([
            'message' => 'Perfil editado com sucesso',
            'data' => new RoleResource($updated),
        ], 200);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $this->roleService->destroyByUuid($id);

        return response()->json(['message' => 'Registro deletado com sucesso.'], 200);
    }
}
