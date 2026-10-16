<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AuditLogResource;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Auditoria
 *
 * Histórico de alterações (quem mudou o quê) no workspace corrente.
 */
class AuditLogController extends BaseController
{
    protected ?string $resourceClass = AuditLogResource::class;

    protected static string $permissionGroup = 'logs';

    protected static array $permissionMethods = [
        'view' => ['index'],
    ];

    public function __construct(AuditLogService $auditLogService)
    {
        parent::__construct($auditLogService);
    }

    /**
     * Listar logs
     *
     * Mais recentes primeiro. `old_values`/`new_values` trazem só os campos alterados.
     *
     * **Permissão:** `logs.view`
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam event string Filtra pela ação. Enum: created, updated, deleted, restored. Example: updated
     * @queryParam actor_uuid string Filtra por quem fez a ação. Example: 2bd8fa57-724f-4cfb-ae33-875afedee0e6
     * @queryParam entity_type string Filtra pelo tipo de registro (parcial, ex.: `role`, `workspace`). Example: role
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "aa11bb22-...",
     *       "action": "updated",
     *       "entity_type": "role",
     *       "entity_uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d",
     *       "entity_name": "Editor",
     *       "actor_uuid": "2bd8fa57-724f-4cfb-ae33-875afedee0e6",
     *       "actor_name": "Ana Souza",
     *       "actor_email": "ana@exemplo.com",
     *       "old_values": {"name": "Redator"},
     *       "new_values": {"name": "Editor"},
     *       "tags": [],
     *       "created_at": "2026-10-15T10:00:00-03:00"
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "logs.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }
}
