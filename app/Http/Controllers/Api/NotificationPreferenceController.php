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
 * Preferência de **e-mail** por família: `invite`, `posts`, `social_accounts`.
 * Central (database) e tempo real (broadcast) ficam sempre ligados; e-mails de cobrança
 * (`billing`) não são configuráveis.
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(
        private NotificationService $service,
    ) {}

    /**
     * Ler preferências
     *
     * @response 200 {"data": {"invite": true, "posts": true, "social_accounts": true, "post_published": false}}
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
     * @bodyParam invite boolean E-mail de convites. Example: true
     * @bodyParam posts boolean E-mail de falha de posts. Example: false
     * @bodyParam social_accounts boolean E-mail de contas sociais. Example: true
     * @bodyParam post_published boolean Aviso in-app (sem e-mail) quando um post seu é publicado; padrão desligado. Example: true
     *
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
