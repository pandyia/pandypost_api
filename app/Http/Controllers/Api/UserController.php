<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    protected ?string $resourceClass = UserResource::class;

    protected static string $permissionGroup = 'users';

    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
        'create' => ['store'],
        'update' => ['update'],
        'delete' => ['destroy'],
        'change_role' => ['changeRole'],
    ];

    public function __construct(
        private UserService $userService
    ) {
        parent::__construct($userService);
    }

    public function store(Request $request): JsonResponse
    {
        $data = app(StoreUserRequest::class)->validated();

        $this->userService->store($data);

        return response()->json(['message' => 'Registro inserido com sucesso.'], 201);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $this->userService->removeFromWorkspace($id);

        return response()->json(['message' => 'Usuário removido com sucesso'], 200);
    }

    public function changeRole(Request $request, string $userUuid): JsonResponse
    {
        $this->userService->changeRole($userUuid, $request->input('role_uuid'));

        return response()->json(['message' => 'Perfil do usuário atualizado com sucesso'], 200);
    }
}
