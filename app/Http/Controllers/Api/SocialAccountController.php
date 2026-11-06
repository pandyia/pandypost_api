<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BaseException;
use App\Exceptions\SocialAccountException;
use App\Http\Resources\SocialAccountResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OAuthStateService;
use App\Services\SocialAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
 *
 * A conta conectada é **do workspace** (não de quem conectou): todos os membros com permissão a enxergam e usam.
 * Contas de TikTok têm o campo `can_publish`; se for `false`, peça para o usuário desconectar e reconectar a conta
 * (falta a permissão `video.publish`). Para montar o formulário de post do TikTok, use `GET /social-accounts/{id}/tiktok/creator-info`.
 */
class SocialAccountController extends BaseController
{
    protected ?string $resourceClass = SocialAccountResource::class;

    protected static string $permissionGroup = 'social_accounts';

    protected static array $permissionMethods = [
        'view' => ['index', 'schedules'],
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
     * A URL leva um `state` de **uso único** que vale 10 minutos e registra o usuário e o workspace corrente: a conta
     * conectada vai para esse workspace. Se o usuário trocar de workspace no meio do fluxo, a conta ainda vai para o de origem.
     *
     * **Permissão:** `social_accounts.connect`
     *
     * @urlParam platform string required Plataforma. Enum: google (YouTube), instagram, tiktok. Example: tiktok
     *
     * @response 200 {"url": "https://www.tiktok.com/v2/auth/authorize/?client_key=...&state=..."}
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
     * `platform` aceito: `google` (YouTube), `instagram` ou `tiktok`.
     *
     * A conta fica no workspace de quem iniciou o fluxo e passa a ser **do workspace**: qualquer membro com a permissão
     * de agendamento pode usá-la, independente de quem conectou. O `state` vale 10 minutos e **uma única vez**;
     * reaproveitado, expirado, adulterado, emitido para **outra plataforma** (o `state` do Google não vale em `/instagram/callback`) ou de quem já saiu do workspace, devolve `?error=Estado OAuth inválido.`.
     * O iniciador precisa ainda ter `social_accounts.connect` no workspace no momento do callback; senão vem `?error=Você não tem permissão para conectar contas neste workspace.`.
     * Erro inesperado (banco, rede) vira `?error=Não foi possível conectar a conta. Tente novamente.`: só mensagens de erro de domínio da API chegam ao `error`.
     * A rota tem limite de 30 requisições por minuto por IP (429).
     *
     * @unauthenticated
     *
     * @urlParam platform string required Plataforma. Enum: google (YouTube), instagram, tiktok. Example: google
     *
     * @queryParam code string required Código de autorização enviado pela plataforma. No-example
     * @queryParam state string required Estado gerado no passo 1 (expira em 10 min e é de uso único). No-example
     *
     * @response 302 scenario="redireciona para o frontend" ""
     * @response 429 scenario="limite de requisições" {"message": "Too Many Attempts."}
     */
    public function callback(string $platform, Request $request, OAuthStateService $oauthState)
    {
        $frontendCallbackUrl = config('app.frontend_url').'/social-accounts/callback';

        // Uso único: o state é consumido aqui, mesmo que o resto falhe.
        try {
            $this->socialAccountService->ensurePlatformSupported($platform);
            $state = $oauthState->consume($request->input('state'), $platform);
        } catch (SocialAccountException $e) {
            // Plataforma inexistente tem texto próprio; qualquer falha do state é a mesma mensagem, sem detalhe.
            $message = $e->getErrorCode() === 'platform_not_supported' ? $e->getMessage() : 'Estado OAuth inválido.';

            return redirect($frontendCallbackUrl.'?error='.urlencode($message));
        }

        $workspace = Workspace::where('uuid', $state['workspace_uuid'])->first();
        $initiator = User::find($state['user_id']);

        // O iniciador precisa continuar com acesso ao workspace (pode ter saído entre o início e o callback).
        if (! $workspace || ! $initiator
            || ! $initiator->accesses()->where('workspace_id', $workspace->id)->exists()) {
            return redirect($frontendCallbackUrl.'?error='.urlencode('Estado OAuth inválido.'));
        }

        // O papel do iniciador pode ter mudado nos 10 minutos do state: precisa ainda poder conectar contas aqui.
        if (! $initiator->isSuperAdmin() && ! $initiator->hasPermissionInWorkspace('social_accounts.connect', $workspace->id)) {
            return redirect($frontendCallbackUrl.'?error='.urlencode('Você não tem permissão para conectar contas neste workspace.'));
        }

        try {
            $this->socialAccountService->syncAccount($initiator, $platform, $request->input('code'), $workspace);

            return redirect($frontendCallbackUrl.'?success=1');
        } catch (BaseException $e) {
            // Exceção de domínio: a mensagem é um texto fixo em pt-BR, própria para exibir.
            return redirect($frontendCallbackUrl.'?error='.urlencode($e->getMessage()));
        } catch (Throwable $e) {
            // QueryException/ConnectionException trazem SQL com tokens ou URL de terceiros: só a classe vai para o log.
            Log::warning("Falha inesperada no callback OAuth ({$platform}, ".$e::class.').');

            return redirect($frontendCallbackUrl.'?error='.urlencode('Não foi possível conectar a conta. Tente novamente.'));
        }
    }

    /**
     * Desconectar conta
     *
     * Remove a conta (de qualquer membro do workspace) e **cancela os agendamentos pendentes** dela, apagando a mídia que nenhum outro post ainda usa. Os posts cancelados deixam de aparecer em `GET /scheduled-posts` (o histórico da conta some junto).
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
     * **Permissão:** `social_accounts.view` **e** `posts.view` (os posts trazem título, legenda e mídia). Falta de qualquer uma devolve 403; o `required_permission` indica a que faltou.
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam platform string Filtra pela plataforma. Example: instagram
     *
     * @response 403 scenario="sem social_accounts.view ou posts.view" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.view"}
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "5f0c2d1e-...",
     *       "platform": "youtube",
     *       "platform_id": "UCx9...",
     *       "nickname": "Canal da Ana",
     *       "avatar": "https://yt3.ggpht.com/...",
     *       "can_publish": true,
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
     *       "can_publish": true,
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
     * @responseField platform string Enum: youtube, instagram, tiktok.
     * @responseField can_publish boolean `false` quando a conta não tem a permissão de publicar (hoje só o TikTok exige `video.publish`; YouTube e Instagram sempre `true`). Com `false`, desabilite o agendamento e peça para reconectar a conta; senão o agendamento é recusado com 422 (`social_account_uuids`) e, se já havia post, ele falha com `tiktok_scope_missing`.
     * @responseField expires_at string|null Quando o token de acesso expira (ISO 8601).
     * @responseField connected_at string Quando a conta foi conectada (ISO 8601).
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
