<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreAccessRequest;
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
        $formRequest = app(StoreAccessRequest::class);
        $workspaceId = $request->user()->resolveCurrentAccess()->workspace_id;

        $this->accessService->store(
            array_merge($formRequest->validated(), ['workspace_id' => $workspaceId])
        );

        return response()->json(['message' => 'Registro inserido com sucesso.'], 201);
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
     * ⚠️ **BUG-01:** hoje este endpoint falha (500 no PostgreSQL), ver `specs/002-security-hardening`. Não use até a correção.
     *
     * **Permissão:** `accesses.update`
     *
     * @urlParam id integer required ID do acesso. Example: 2
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        return parent::update($request, $id);
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
