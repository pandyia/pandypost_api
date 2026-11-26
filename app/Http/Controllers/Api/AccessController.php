<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreAccessRequest;
use App\Http\Requests\UpdateAccessRequest;
use App\Services\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Acessos
 *
 * Vínculo bruto usuário ↔ perfil no workspace corrente. **Usa IDs numéricos**, não uuid.
 *
 * <aside class="notice">Para o fluxo normal, use convites (<code>/invites</code>) e troca de perfil
 * (<code>PATCH /users/{user}/role</code>). A permanência destas rotas está em discussão em <code>specs/002</code> (Q2).</aside>
 */
class AccessController extends BaseController
{
    protected static string $permissionGroup = 'accesses';

    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
        'create' => ['store'],
        'update' => ['update'],
        'delete' => ['destroy'],
    ];

    protected static array $formRequests = [
        'store' => StoreAccessRequest::class,
        'update' => UpdateAccessRequest::class,
    ];

    public function __construct(
        private AccessService $accessService
    ) {
        parent::__construct($accessService);
    }

    /**
     * Listar acessos
     *
     * **Permissão:** `accesses.view`
     *
     * <aside class="notice">Formato: paginador "cru" do Laravel (itens em <code>data</code>, paginação na raiz).</aside>
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     *
     * @response 200 {"current_page": 1, "data": [{"id": 2, "user_id": 2, "role_id": 2, "workspace_id": 2, "created_at": "2026-10-10T16:52:25.000000Z", "updated_at": "2026-10-10T16:52:25.000000Z"}], "per_page": 1, "total": 1, "last_page": 1}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "accesses.view"}
     */
    public function index(Request $request): Response
    {
        $data = $this->accessService->workspaceAccesses($request->input());

        return response($data, 200);
    }

    /**
     * Criar acesso
     *
     * Coloca um usuário no workspace corrente com um perfil.
     *
     * **Permissão:** `accesses.create`
     *
     * @bodyParam user_id integer required ID do usuário. Example: 5
     * @bodyParam role_id integer required ID de um perfil do workspace corrente. Example: 2
     *
     * @response 201 {"message": "Registro inserido com sucesso."}
     * @response 422 {"message": "O perfil informado não existe neste workspace.", "errors": {"role_id": ["O perfil informado não existe neste workspace."]}}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "accesses.create"}
     */
    public function store(Request $request): JsonResponse
    {
        return parent::store($request);
    }

    /**
     * Ver acesso
     *
     * **Permissão:** `accesses.view`
     *
     * @urlParam id integer required ID do acesso. Example: 2
     *
     * @response 200 {"id": 2, "user_id": 2, "role_id": 2, "workspace_id": 2, "created_at": "2026-10-10T16:52:25.000000Z", "updated_at": "2026-10-10T16:52:25.000000Z"}
     */
    public function show(int|string $id): Response
    {
        return parent::show($id);
    }

    /**
     * Editar acesso
     *
     * Troca o perfil do acesso. Só `role_id` é aceito, e o perfil precisa ser do workspace corrente.
     *
     * **Permissão:** `accesses.update`
     *
     * @urlParam id integer required ID do acesso. Example: 2
     *
     * @bodyParam role_id integer required ID de um perfil do workspace corrente. Example: 3
     *
     * @response 200 {"message": "Registro atualizado com sucesso."}
     * @response 422 {"message": "O perfil informado não existe neste workspace.", "errors": {"role_id": ["O perfil informado não existe neste workspace."]}}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "accesses.update"}
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        // Só o perfil, validado no workspace corrente; findById já filtra pelo workspace corrente.
        $data = $this->validatedInput('update');
        $access = $this->accessService->findById($id);

        $this->accessService->update($access, $data);

        return response()->json(['message' => 'Registro atualizado com sucesso.'], 200);
    }

    /**
     * Excluir acesso
     *
     * **Permissão:** `accesses.delete`
     *
     * @urlParam id integer required ID do acesso. Example: 2
     *
     * @response 200 {"message": "Registro deletado com sucesso."}
     */
    public function destroy(int|string $id): JsonResponse
    {
        return parent::destroy($id);
    }
}
