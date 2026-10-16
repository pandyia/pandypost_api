<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Usuários
 *
 * Membros do workspace corrente. Para colocar alguém num workspace, prefira **convites**.
 */
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

    /**
     * Criar usuário
     *
     * Cria a conta de um usuário (com senha definida por quem cria).
     *
     * **Permissão:** `users.create`
     *
     * @response 201 {"message": "Registro inserido com sucesso."}
     * @response 422 {"message": "Este e-mail já está cadastrado em nossa plataforma.", "errors": {"email": ["Este e-mail já está cadastrado em nossa plataforma."]}}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "users.create"}
     */
    public function store(Request $request): JsonResponse
    {
        $data = app(StoreUserRequest::class)->validated();

        $this->userService->store($data);

        return response()->json(['message' => 'Registro inserido com sucesso.'], 201);
    }

    /**
     * Remover usuário do workspace
     *
     * Tira o usuário do workspace corrente (a conta dele continua existindo). Se ele estava usando esse workspace,
     * é deslogado e volta para o workspace pessoal.
     *
     * **Permissão:** `users.delete`
     *
     * @urlParam id string required UUID do usuário. Example: 2bd8fa57-724f-4cfb-ae33-875afedee0e6
     *
     * @response 200 {"message": "Usuário removido com sucesso"}
     * @response 400 scenario="remover a si mesmo" {"error": "cannot_remove_yourself", "message": "Você não pode remover a si mesmo do workspace."}
     * @response 400 scenario="último membro" {"error": "workspace_must_have_at_least_one_user", "message": "O workspace deve ter pelo menos um usuário."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "users.delete"}
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->userService->removeFromWorkspace($id);

        return response()->json(['message' => 'Usuário removido com sucesso'], 200);
    }

    /**
     * Trocar perfil do usuário
     *
     * Muda o perfil (role) do usuário no workspace corrente.
     *
     * **Permissão:** `users.change_role`
     *
     * @urlParam user string required UUID do usuário. Example: 2bd8fa57-724f-4cfb-ae33-875afedee0e6
     *
     * @bodyParam role_uuid string required UUID de um perfil do workspace corrente. Example: 9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d
     *
     * @response 200 {"message": "Perfil do usuário atualizado com sucesso"}
     * @response 404 scenario="perfil de outro workspace" {"error": "profile_not_found", "message": "Perfil não encontrado no workspace."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "users.change_role"}
     */
    public function changeRole(Request $request, string $userUuid): JsonResponse
    {
        $this->userService->changeRole($userUuid, $request->input('role_uuid'));

        return response()->json(['message' => 'Perfil do usuário atualizado com sucesso'], 200);
    }

    /**
     * Listar usuários
     *
     * **Permissão:** `users.view`
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam name string Filtra por nome (parcial). Example: ana
     * @queryParam email string Filtra por email (parcial). Example: @exemplo.com
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "2bd8fa57-724f-4cfb-ae33-875afedee0e6",
     *       "name": "Ana Souza",
     *       "email": "ana@exemplo.com",
     *       "email_verified_at": "2026-10-10 16:52:25",
     *       "created_at": "2026-10-10 16:52:25",
     *       "updated_at": "2026-10-15 21:39:45",
     *       "is_active": true,
     *       "remember_me": false,
     *       "access_id": 2,
     *       "current_workspace": {"uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833", "name": "Agência Pandy"}
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     *
     * @responseField current_workspace object|null Workspace corrente **do usuário listado** (não o seu).
     *
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "users.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    /**
     * Ver usuário
     *
     * ⚠️ **BUG-01:** hoje este endpoint falha (500 no PostgreSQL), ver `specs/002-security-hardening`. Não use até a correção.
     *
     * **Permissão:** `users.view`
     *
     * @urlParam id string required UUID do usuário. Example: 2bd8fa57-724f-4cfb-ae33-875afedee0e6
     *
     * @response 200 {"data": {
     *       "uuid": "2bd8fa57-724f-4cfb-ae33-875afedee0e6",
     *       "name": "Ana Souza",
     *       "email": "ana@exemplo.com",
     *       "email_verified_at": "2026-10-10 16:52:25",
     *       "created_at": "2026-10-10 16:52:25",
     *       "updated_at": "2026-10-15 21:39:45",
     *       "is_active": true,
     *       "remember_me": false,
     *       "access_id": 2,
     *       "current_workspace": {"uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833", "name": "Agência Pandy"}
     *     }}
     */
    public function show(int|string $id): Response
    {
        return parent::show($id);
    }

    /**
     * Editar usuário
     *
     * ⚠️ **BUG-01:** hoje este endpoint falha (500 no PostgreSQL), ver `specs/002-security-hardening`. Não use até a correção.
     *
     * **Permissão:** `users.update`
     *
     * @urlParam id string required UUID do usuário. Example: 2bd8fa57-724f-4cfb-ae33-875afedee0e6
     *
     * @bodyParam name string Nome. Example: Ana Souza
     *
     * @response 200 {"message": "Registro atualizado com sucesso."}
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        return parent::update($request, $id);
    }
}
