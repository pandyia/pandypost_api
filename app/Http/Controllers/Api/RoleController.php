<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Perfis e permissões
 *
 * Perfis (roles) agrupam permissões dentro do workspace corrente. Cada membro tem um perfil por workspace.
 * Mudanças em perfis são transmitidas em tempo real (ver "Tempo real" na introdução).
 */
class RoleController extends BaseController
{
    protected ?string $resourceClass = RoleResource::class;

    protected static string $permissionGroup = 'roles';

    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
        'create' => ['store'],
        'update' => ['update'],
        'delete' => ['destroy'],
    ];

    public function __construct(
        private RoleService $roleService
    ) {
        parent::__construct($roleService);
    }

    /**
     * Ver perfil
     *
     * Com permissões e membros.
     *
     * **Permissão:** `roles.view`
     *
     * @urlParam id string required UUID do perfil. Example: 9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d
     *
     * @response 200 {"data": {
     *     "uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d",
     *     "name": "Editor",
     *     "permissions": [
     *       {"name": "posts.view", "description": "Visualizar posts", "group": "posts"},
     *       {"name": "posts.create", "description": "Criar posts", "group": "posts"}
     *     ],
     *     "user_count": 1,
     *     "users": [{"uuid": "2bd8fa57-724f-4cfb-ae33-875afedee0e6", "name": "Ana Souza", "email": "ana@exemplo.com"}]
     *   }}
     */
    public function show(int|string $id): Response
    {
        $role = $this->roleService->findByUuid($id)->load(['permissions', 'users']);

        return (new RoleResource($role))->response();
    }

    /**
     * Criar perfil
     *
     * **Permissão:** `roles.create`
     *
     * @bodyParam name string required Nome do perfil, único no workspace. Example: Editor
     * @bodyParam permissions string[] Nomes das permissões (ver `GET /permissions`). Substitui a lista inteira. Example: ["posts.view", "posts.create"]
     *
     * @response 201 {"message": "Perfil criado com sucesso", "data": {
     *     "uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d",
     *     "name": "Editor",
     *     "permissions": [
     *       {"name": "posts.view", "description": "Visualizar posts", "group": "posts"},
     *       {"name": "posts.create", "description": "Criar posts", "group": "posts"}
     *     ],
     *     "user_count": 0,
     *     "users": []
     *   }}
     * @response 422 scenario="nome repetido" {"error": "role_name_already_exists", "message": "Já existe um perfil com esse nome neste workspace."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "roles.create"}
     */
    public function store(Request $request): JsonResponse
    {
        $role = $this->roleService->createRole($request->all());

        return response()->json([
            'message' => 'Perfil criado com sucesso',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Editar perfil
     *
     * Envie só o que mudar. Se mandar `permissions`, a lista é substituída.
     *
     * **Permissão:** `roles.update`
     *
     * @urlParam id string required UUID do perfil. Example: 9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d
     *
     * @bodyParam name string Novo nome, único no workspace. Example: Editor sênior
     * @bodyParam permissions string[] Nomes das permissões. Example: ["posts.view"]
     *
     * @response 200 {"message": "Perfil editado com sucesso", "data": {
     *     "uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d",
     *     "name": "Editor",
     *     "permissions": [
     *       {"name": "posts.view", "description": "Visualizar posts", "group": "posts"},
     *       {"name": "posts.create", "description": "Criar posts", "group": "posts"}
     *     ],
     *     "user_count": 1,
     *     "users": [{"uuid": "2bd8fa57-724f-4cfb-ae33-875afedee0e6", "name": "Ana Souza", "email": "ana@exemplo.com"}]
     *   }}
     * @response 422 scenario="nome repetido" {"error": "role_name_already_exists", "message": "Já existe um perfil com esse nome neste workspace."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "roles.update"}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $updated = $this->roleService->updateRole($request->all(), $id);

        return response()->json([
            'message' => 'Perfil editado com sucesso',
            'data' => new RoleResource($updated),
        ], 200);
    }

    /**
     * Excluir perfil
     *
     * Não é possível excluir perfil com membros.
     *
     * **Permissão:** `roles.delete`
     *
     * @urlParam id string required UUID do perfil. Example: 9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d
     *
     * @response 200 {"message": "Registro deletado com sucesso."}
     * @response 409 scenario="perfil com membros" {"error": "profile_has_linked_users", "message": "Não é possível excluir um perfil com usuários vinculados."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "roles.delete"}
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->roleService->destroyByUuid($id);

        return response()->json(['message' => 'Registro deletado com sucesso.'], 200);
    }

    /**
     * Listar perfis
     *
     * Só nome e uuid. Para ver permissões e membros, use o detalhe.
     *
     * **Permissão:** `roles.view`
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam name string Filtra por nome (parcial). Example: edit
     *
     * @response 200 {
     *   "data": [{"uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d", "name": "Editor"}],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "roles.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }
}
