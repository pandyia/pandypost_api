<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Http\Resources\NotificationPreferenceResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Notificações
 *
 * Duas escolhas independentes por família (`invite`, `posts`, `social_accounts`, `post_published`): **e-mail**
 * (campos soltos) e **no app** (`in_app`: central + tempo real). Cobrança (`billing`) é sempre
 * e-mail e no app, não configurável.
 *
 * **Permissões:** as preferências exigem `notifications.view` (ler) e `notifications.update` (alterar); super admin ignora.
 * Receber notificações (`/api/notifications*`: listar, não lidas, marcar como lida, excluir) continua livre para
 * qualquer usuário logado, sem permissão. A permissão só controla a aba de preferências: quem não a tem
 * continua recebendo avisos conforme as preferências já salvas (ou o padrão).
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(
        private NotificationService $service,
    ) {}

    /**
     * Ler preferências
     *
     * Retorna as preferências de e-mail e de aviso no app do usuário logado.
     *
     * **Permissão:** `notifications.view`
     *
     * @response 200 {"data": {"invite": true, "posts": true, "social_accounts": true, "post_published": true, "in_app": {"invite": true, "posts": true, "social_accounts": true, "post_published": false}}}
     */
    public function show(Request $request): NotificationPreferenceResource
    {
        return new NotificationPreferenceResource($request->user()->notificationSettings());
    }

    /**
     * Alterar preferências
     *
     * Envie só as famílias que quer mudar.
     *
     * **Permissão:** `notifications.update`
     *
     * @bodyParam invite boolean E-mail de convites. Example: true
     * @bodyParam posts boolean E-mail de falha de posts. Example: false
     * @bodyParam social_accounts boolean E-mail de contas sociais. Example: true
     * @bodyParam in_app object Aviso no app (sino + tempo real) por família; envie só o que muda. Example: {"posts": false}
     * @bodyParam in_app.invite boolean Aviso no app de convites. Example: true
     * @bodyParam in_app.posts boolean Aviso no app de falha de posts. Example: false
     * @bodyParam in_app.social_accounts boolean Aviso no app de contas sociais. Example: true
     * @bodyParam post_published boolean E-mail quando um post seu é publicado; padrão ligado. Example: true
     * @bodyParam in_app.post_published boolean Aviso no app quando um post seu é publicado; padrão desligado (opt-in). Example: true
     *
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "notifications.update"}
     * @response 422 {"message": "Os e-mails de cobrança não podem ser desativados.", "errors": {"billing": ["Os e-mails de cobrança não podem ser desativados."]}}
     */
    public function update(Request $request): JsonResponse
    {
        $data = app(UpdateNotificationPreferencesRequest::class)->validated();

        return response()->json([
            'message' => 'Preferências atualizadas.',
            'data' => new NotificationPreferenceResource($this->service->updatePreferences($request->user(), $data)),
        ]);
    }
}
