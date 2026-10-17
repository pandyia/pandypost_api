<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Notificações
 *
 * Central de notificações do usuário logado, por workspace: lista as do workspace corrente
 * mais os convites recebidos (`workspace_invite`), que aparecem em qualquer workspace.
 * Não exige permissão de grupo. Notificação de outro usuário responde 404.
 *
 * Tipos: `workspace_invite`, `post_failed`, `payment_failed`, `subscription_canceled`,
 * `social_account_disconnected`. Em tempo real: canal privado `App.Models.User.{id}`,
 * evento `Illuminate\Notifications\Events\BroadcastNotificationCreated`.
 */
class NotificationController extends BaseController
{
    protected ?string $resourceClass = NotificationResource::class;

    public function __construct(
        private NotificationService $notificationService,
    ) {
        parent::__construct($notificationService);
    }

    /**
     * Listar notificações
     *
     * @queryParam read string `all` (padrão), `unread` ou `read`. Example: unread
     * @queryParam type string Tipo da notificação. Example: post_failed
     * @queryParam per_page integer De 1 a 100 (padrão 15). Example: 15
     */
    public function index(Request $request): Response
    {
        $filters = app(ListNotificationsRequest::class)->validated();
        $user = $request->user();

        return NotificationResource::collection($this->notificationService->paginate($user, $filters))
            ->additional(['unread_count' => $this->notificationService->unreadCount($user)])
            ->response();
    }

    /**
     * Listar não lidas
     *
     * Até 50 mais recentes; `total_unread` traz o total real.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => NotificationResource::collection($this->notificationService->unread($user)),
            'total_unread' => $this->notificationService->unreadCount($user),
        ]);
    }

    /**
     * Marcar como lida
     */
    public function read(Request $request, string $notification): JsonResponse
    {
        $model = $this->notificationService->markAsRead($request->user(), $notification);

        return response()->json([
            'message' => 'Notificação marcada como lida.',
            'data' => new NotificationResource($model),
        ]);
    }

    /**
     * Marcar todas como lidas
     *
     * Vale para as notificações visíveis no workspace corrente.
     */
    public function readAll(Request $request): JsonResponse
    {
        $updated = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'message' => 'Notificações marcadas como lidas.',
            'data' => ['updated' => $updated],
        ]);
    }

    /**
     * Remover notificação
     */
    public function destroy(int|string $notification): JsonResponse
    {
        $this->notificationService->delete(request()->user(), (string) $notification);

        return response()->json(['message' => 'Notificação removida.']);
    }
}
