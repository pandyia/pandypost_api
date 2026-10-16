<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PermissionResource;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Perfis e permissões
 */
class PermissionController extends BaseController
{
    protected ?string $resourceClass = PermissionResource::class;

    protected static string $permissionGroup = 'permissions';

    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
    ];

    public function __construct(
        private PermissionService $permissionService
    ) {
        parent::__construct($permissionService);
    }

    /**
     * Listar permissões
     *
     * Catálogo de todas as permissões do sistema. Use `group` para montar a tela de edição de perfil.
     *
     * **Permissão:** `permissions.view`
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     *
     * @response 200 {
     *   "data": [
     *     {"name": "posts.view", "description": "Visualizar posts", "group": "posts"},
     *     {"name": "posts.create", "description": "Criar posts", "group": "posts"}
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 2}
     * }
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "permissions.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    /**
     * Ver permissão
     *
     * **Permissão:** `permissions.view`
     *
     * @urlParam permission integer required **ID numérico** da permissão (exceção à regra do uuid). Example: 1
     *
     * @response 200 {"data": {"name": "posts.view", "description": "Visualizar posts", "group": "posts"}}
     */
    public function show(int|string $id): Response
    {
        return parent::show($id);
    }
}
