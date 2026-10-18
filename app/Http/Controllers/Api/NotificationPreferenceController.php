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
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(
        private NotificationService $service,
    ) {}

    /**
     * Ler preferências
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
