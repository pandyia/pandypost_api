<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreInviteRequest;
use App\Http\Resources\InviteResource;
use App\Http\Resources\ReceivedInviteResource;
use App\Models\Invite;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Convites
 *
 * Convidar pessoas para o workspace corrente e responder convites recebidos.
 * O convidado recebe um email; ao aceitar, entra no workspace com o perfil escolhido.
 *
 * `status`: `pending`, `accepted`, `declined` ou `expired`.
 */
class InviteController extends BaseController
{
    protected ?string $resourceClass = InviteResource::class;

    protected static string $permissionGroup = 'invites';

    protected static array $permissionMethods = [
        'view' => ['index'],
        'create' => ['send'],
        'delete' => ['destroy'],
    ];

    public function __construct(
        private InviteService $inviteService,
    ) {
        parent::__construct($inviteService);
    }

    /**
     * Enviar convite
     *
     * Convida um email (com ou sem conta) para o workspace corrente.
     *
     * **Permissão:** `invites.create`
     *
     * @bodyParam email string required Email do convidado. Example: joao@exemplo.com
     * @bodyParam role_uuid string required UUID do perfil que ele terá. Example: 9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d
     *
     * @response 200 {"message": "Convite enviado com sucesso"}
     * @response 409 scenario="já convidado" {"error": "already_invited", "message": "Usuário já foi convidado para este workspace."}
     * @response 409 scenario="já é membro" {"error": "already_member", "message": "Este usuário já é membro deste workspace."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "invites.create"}
     */
    public function send(StoreInviteRequest $request): JsonResponse
    {
        $this->inviteService->sendInvite($request->validated());

        return response()->json(['message' => 'Convite enviado com sucesso'], 200);
    }

    /**
     * Convites recebidos
     *
     * Convites para o usuário logado, de qualquer workspace. Não exige permissão.
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam sender string Filtra pelo email de quem convidou (parcial). Example: ana@
     * @queryParam workspace_uuid string Filtra pelo workspace. Example: 58d7d732-10a4-491d-9864-b4d85dfcf833
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "c7e9...",
     *       "invitedBy": {"uuid": "2bd8...", "name": "Ana Souza", "email": "ana@exemplo.com"},
     *       "workspace": {"uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833", "name": "Agência Pandy"},
     *       "role": {"uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d", "name": "Editor"},
     *       "status": "pending",
     *       "created_at": "2026-10-15 10:00:00",
     *       "expires_at": "2026-10-22 10:00:00"
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     */
    public function received(Request $request): Response
    {
        $data = $this->inviteService->receivedInvites($request->input());

        return ReceivedInviteResource::collection($data)->response();
    }

    /**
     * Aceitar convite
     *
     * O usuário passa a ser membro do workspace. O workspace corrente não muda.
     *
     * @urlParam invite_uuid string required UUID do convite. Example: c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b
     *
     * @response 200 {"message": "Convite aceito com sucesso"}
     * @response 403 scenario="convite de outra pessoa" {"error": "not_your_invite", "message": "Este convite pertence a outro usuário."}
     * @response 400 scenario="já respondido ou expirado" {"error": "invite_not_pending", "message": "Este convite já foi processado ou expirou."}
     */
    public function accept(Invite $invite): JsonResponse
    {
        $this->inviteService->accept($invite);

        return response()->json(['message' => 'Convite aceito com sucesso']);
    }

    /**
     * Recusar convite
     *
     * @urlParam invite_uuid string required UUID do convite. Example: c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b
     *
     * @response 200 {"message": "Convite rejeitado com sucesso"}
     * @response 403 scenario="convite de outra pessoa" {"error": "not_your_invite", "message": "Este convite pertence a outro usuário."}
     * @response 400 scenario="já respondido ou expirado" {"error": "invite_not_pending", "message": "Este convite já foi processado ou expirou."}
     */
    public function decline(Invite $invite): JsonResponse
    {
        $this->inviteService->decline($invite);

        return response()->json(['message' => 'Convite rejeitado com sucesso']);
    }

    /**
     * Convites enviados
     *
     * Convites do workspace corrente.
     *
     * **Permissão:** `invites.view`
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam role_uuid string Filtra pelo perfil. Example: 9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d
     * @queryParam recipient string Filtra pelo email do convidado (parcial). Example: joao@
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "c7e9...",
     *       "recipient": {"uuid": "a1b2...", "name": "João Lima", "email": "joao@exemplo.com"},
     *       "role": {"uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d", "name": "Editor"},
     *       "status": "pending",
     *       "created_at": "2026-10-15 10:00:00",
     *       "updated_at": "2026-10-15 10:00:00",
     *       "expires_at": "2026-10-22 10:00:00"
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     *
     * @responseField recipient object Se o convidado ainda não tem conta, vem só `{"email": "..."}`.
     *
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "invites.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }
}
