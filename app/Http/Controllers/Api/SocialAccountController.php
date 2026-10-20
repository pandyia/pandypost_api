<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SocialAccountResource;
use App\Models\Workspace;
use App\Services\SocialAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Contas sociais
 *
 * Contas de YouTube, Instagram e TikTok conectadas ao workspace corrente.
 *
 * **Como conectar uma conta (OAuth em popup):**
 * 1. `GET /social-accounts/{platform}/auth` → recebe `url`.
 * 2. Abra `url` num popup. O usuário autoriza na plataforma.
 * 3. A plataforma chama o callback da API, que redireciona o popup para
 *    `{FRONTEND_URL}/social-accounts/callback?success=1` ou `?error=mensagem`.
 * 4. Nessa página, avise a janela principal (ex.: `window.opener.postMessage`) e recarregue a lista.
 */
class SocialAccountController extends BaseController
{
    protected ?string $resourceClass = SocialAccountResource::class;

    protected static string $permissionGroup = 'social_accounts';

    protected static array $permissionMethods = [
        'view' => ['index'],
        'connect' => ['redirect'],
        'disconnect' => ['destroy'],
    ];

    public function __construct(
        private SocialAccountService $socialAccountService
    ) {
        parent::__construct($socialAccountService);
    }

    /**
     * Iniciar conexão (URL de OAuth)
     *
     * Devolve a URL de autorização da plataforma. **Não redireciona**: abra a URL num popup (passo 1 do fluxo acima).
     *
     * **Permissão:** `social_accounts.connect`
     *
     * @urlParam platform string required Plataforma. Enum: youtube, instagram, tiktok. Example: youtube
     *
     * @response 200 {"url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...&state=..."}
     * @response 400 scenario="plataforma inválida" {"error": "platform_not_supported", "message": "Plataforma 'x' não é suportada."}
     * @response 500 scenario="credenciais da plataforma ausentes" {"error": "oauth_initialization_failed", "message": "Falha ao iniciar o fluxo OAuth."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "social_accounts.connect"}
     */
    public function redirect(Request $request, string $platform): JsonResponse
    {
        $url = $this->socialAccountService->getRedirectUrl($platform, $request->user());

        return response()->json(['url' => $url]);
    }

    /**
     * Callback do OAuth
     *
     * **Não chame pelo frontend.** É a URL de retorno cadastrada na plataforma. A API conecta a conta e redireciona
     * o popup para `{FRONTEND_URL}/social-accounts/callback` com:
     *
     * - `?success=1` → conta conectada;
     * - `?error=<mensagem>` → falha (mensagem pronta para exibir).
     *
     * @unauthenticated
     *
     * @urlParam platform string required Plataforma. Enum: youtube, instagram, tiktok. Example: youtube
     *
     * @queryParam code string required Código de autorização enviado pela plataforma. No-example
     * @queryParam state string required Estado gerado no passo 1. No-example
     *
     * @response 302 scenario="redireciona para o frontend" ""
     */
    public function callback(string $platform, Request $request)
    {
        $frontendCallbackUrl = config('app.frontend_url').'/social-accounts/callback';

        $state = $request->input('state');

        if (! $state) {
            return redirect($frontendCallbackUrl.'?error='.urlencode('Estado OAuth inválido.'));
        }

        try {
            $workspaceUuid = decrypt($state);
        } catch (\Exception $e) {
            return redirect($frontendCallbackUrl.'?error='.urlencode('Estado OAuth inválido.'));
        }

        $workspace = Workspace::where('uuid', $workspaceUuid)->first();

        if (! $workspace) {
            return redirect($frontendCallbackUrl.'?error='.urlencode('Workspace não encontrado.'));
        }

        try {
            $this->socialAccountService->syncAccount($workspace, $platform, $request->input('code'));

            return redirect($frontendCallbackUrl.'?success=1');
        } catch (\Exception $e) {
            return redirect($frontendCallbackUrl.'?error='.urlencode($e->getMessage()));
        }
    }

    /**
     * Desconectar conta
     *
     * Remove a conta e **cancela os agendamentos pendentes** dela.
     *
     * **Permissão:** `social_accounts.disconnect`
     *
     * @urlParam socialAccount string required UUID da conta. Example: 5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 200 {"message": "Conta social desconectada com sucesso."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "social_accounts.disconnect"}
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->socialAccountService->disconnect($id);

        return response()->json([
            'message' => 'Conta social desconectada com sucesso.',
        ]);
    }

    /**
     * Contas com seus agendamentos
     *
     * Mesma lista de contas, cada uma com `scheduled_posts` (formato de [Agendamentos](#agendamentos)). Útil para o calendário.
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam platform string Filtra pela plataforma. Example: instagram
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "5f0c2d1e-...",
     *       "platform": "youtube",
     *       "platform_id": "UCx9...",
     *       "nickname": "Canal da Ana",
     *       "avatar": "https://yt3.ggpht.com/...",
     *       "expires_at": "2026-10-15T15:00:00-03:00",
     *       "connected_at": "2026-10-01T10:00:00-03:00",
     *       "is_stale": false,
     *       "days_since_last_activity": 1,
     *       "scheduled_posts": [{"uuid": "e1d2...", "platform": "youtube", "title": "Meu vídeo", "status": "pending", "scheduled_at": "2026-10-20T18:00:00-03:00"}]
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     *
     * @responseField data[].scheduled_posts[].can_retry boolean `true` quando o post falhou e o arquivo ainda está guardado: mostre o botão Reenviar (`POST /scheduled-posts/{uuid}/retry`).
     * @responseField data[].scheduled_posts[].retry_until string|null Até quando o arquivo fica guardado para reenvio (5 dias após a falha).
     * @responseField data[].scheduled_posts[].platform_post_url string|null Link do post publicado. No Instagram vem do permalink da rede; pode ser `null` se não foi possível obtê-lo.
     */
    public function schedules(Request $request): Response
    {
        $data = $this->socialAccountService->paginate(
            $request->input(),
            with: ['scheduledPosts'],
        );

        return SocialAccountResource::collection($data)->response();
    }

    /**
     * Listar contas conectadas
     *
     * **Permissão:** `social_accounts.view`
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam platform string Filtra pela plataforma. Example: youtube
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "5f0c2d1e-...",
     *       "platform": "youtube",
     *       "platform_id": "UCx9...",
     *       "nickname": "Canal da Ana",
     *       "avatar": "https://yt3.ggpht.com/...",
     *       "expires_at": "2026-10-15T15:00:00-03:00",
     *       "connected_at": "2026-10-01T10:00:00-03:00",
     *       "is_stale": false,
     *       "days_since_last_activity": 1
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     *
     * @responseField is_stale boolean `true` se a conta está 2+ dias sem postar ou nunca postou.
     * @responseField days_since_last_activity integer|null Dias desde o último post (0 se há post agendado no futuro). `null` = nunca postou.
     *
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "social_accounts.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }
}
