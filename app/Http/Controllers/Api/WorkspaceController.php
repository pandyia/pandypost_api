<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Http\Resources\SwitchWorkspaceResource;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Workspaces
 *
 * Workspaces (times) dos quais o usuário participa. Todo usuário tem um workspace pessoal, criado no cadastro,
 * que não pode ser excluído. Quem cria um workspace vira "Admin" dele.
 */
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

    /**
     * Criar workspace
     *
     * O usuário logado vira Admin do novo workspace. O workspace corrente **não** muda: use `switch` depois.
     *
     * **Permissão:** `workspaces.create`
     *
     * @bodyParam name string required Nome (máx. 255). Não pode repetir o nome de outro workspace do usuário. Example: Agência Pandy
     *
     * @response 201 {"message": "Registro inserido com sucesso."}
     * @response 422 scenario="nome repetido" {"error": "workspace_name_already_exists", "message": "Já existe um workspace com esse nome."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "workspaces.create"}
     */
    public function store(Request $request): JsonResponse
    {
        $data = app(StoreWorkspaceRequest::class)->validated();

        $this->workspaceService->store($data);

        return response()->json(['message' => 'Registro inserido com sucesso.'], 201);
    }

    /**
     * Renomear workspace
     *
     * **Permissão:** `workspaces.update`
     *
     * @urlParam id string required UUID do workspace. Example: 58d7d732-10a4-491d-9864-b4d85dfcf833
     *
     * @bodyParam name string required Novo nome (máx. 255). Example: Agência Pandy 2
     *
     * @response 200 {"message": "Registro atualizado com sucesso."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "workspaces.update"}
     */
    public function update(Request $request, $uuid): JsonResponse
    {
        $data = app(UpdateWorkspaceRequest::class)->validated();

        $this->workspaceService->updateByUuid($data, $uuid);

        return response()->json(['message' => 'Registro atualizado com sucesso.'], 200);
    }

    /**
     * Excluir workspace
     *
     * O workspace pessoal não pode ser excluído.
     *
     * **Permissão:** `workspaces.delete`
     *
     * @urlParam id string required UUID do workspace. Example: 58d7d732-10a4-491d-9864-b4d85dfcf833
     *
     * @response 204 scenario="sucesso" ""
     * @response 400 scenario="workspace pessoal" {"error": "workspace_cannot_deleted", "message": "Workspace pessoal não pode ser excluído."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "workspaces.delete"}
     */
    public function destroy($uuid): JsonResponse
    {
        $this->workspaceService->destroyByUuid($uuid);

        return response()->json(['message' => 'Registro deletado com sucesso.'], 204);
    }

    /**
     * Trocar workspace corrente
     *
     * Define o workspace em que o usuário vai trabalhar. A partir daí, todas as rotas leem e gravam nele.
     * Atualize na interface o perfil e as permissões com o que vier na resposta.
     *
     * **Permissão:** `workspaces.view`
     *
     * @urlParam workspace string required UUID do workspace de destino. Example: 58d7d732-10a4-491d-9864-b4d85dfcf833
     *
     * @response 200 {
     *   "data": {
     *     "current_workspace": {"uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833", "name": "Agência Pandy"},
     *     "role": "Editor",
     *     "permissions": ["posts.view", "posts.create"]
     *   }
     * }
     * @response 403 scenario="não é membro" {"error": "user_not_linked_to_workspace", "message": "Você não está vinculado a este workspace."}
     */
    public function switchWorkspace(Request $request, string $uuid)
    {
        $access = $this->workspaceService->switchWorkspace($request->user(), $uuid);

        return new SwitchWorkspaceResource($access);
    }

    /**
     * Listar workspaces
     *
     * Workspaces dos quais o usuário é membro.
     *
     * **Permissão:** `workspaces.view`
     *
     * <aside class="notice">Formato: paginador "cru" do Laravel (itens em <code>data</code>, paginação na raiz).</aside>
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     *
     * @response 200 {
     *   "current_page": 1,
     *   "data": [
     *     {
     *       "uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833",
     *       "name": "Agência Pandy",
     *       "is_personal_team": false,
     *       "stripe_id": null,
     *       "pm_type": null,
     *       "pm_last_four": null,
     *       "trial_ends_at": null,
     *       "created_at": "2026-10-10T16:52:25.000000Z",
     *       "updated_at": "2026-10-10T16:52:25.000000Z"
     *     }
     *   ],
     *   "per_page": 1,
     *   "total": 1,
     *   "last_page": 1
     * }
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "workspaces.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    /**
     * Ver workspace
     *
     * ⚠️ **BUG-01:** hoje este endpoint falha (500 no PostgreSQL), ver `specs/002-security-hardening`. Não use até a correção.
     *
     * **Permissão:** `workspaces.view`
     *
     * @urlParam id string required UUID do workspace. Example: 58d7d732-10a4-491d-9864-b4d85dfcf833
     *
     * @response 200 {
     *       "uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833",
     *       "name": "Agência Pandy",
     *       "is_personal_team": false,
     *       "stripe_id": null,
     *       "pm_type": null,
     *       "pm_last_four": null,
     *       "trial_ends_at": null,
     *       "created_at": "2026-10-10T16:52:25.000000Z",
     *       "updated_at": "2026-10-10T16:52:25.000000Z"
     *     }
     */
    public function show(int|string $id): Response
    {
        return parent::show($id);
    }
}
