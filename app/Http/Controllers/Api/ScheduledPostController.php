<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\GenerateUploadUrlRequest;
use App\Http\Requests\RetryScheduledPostRequest;
use App\Http\Requests\StoreScheduledPostRequest;
use App\Http\Resources\ScheduledPostResource;
use App\Services\ScheduledPostService;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Agendamentos
 *
 * Publicação e agendamento de vídeos/imagens nas contas conectadas.
 *
 * **Fluxo de publicação:**
 * 1. `POST /scheduled-posts/upload-url` → recebe `url` e `path`.
 * 2. Envie o arquivo com `PUT {url}` direto para o S3, com o header `Content-Type` igual ao `content_type` pedido
 *    (e os `headers` devolvidos). Não passa pela API.
 * 3. `POST /scheduled-posts` com o `path` em `media_storage_path` e as contas escolhidas.
 *    É criado **um post por conta**. Sem `scheduled_at`, publica na hora.
 *
 * `status`: `pending` → `processing` → `published` ou `failed`; `cancelled` quando cancelado.
 * Post `failed` guarda o arquivo por 5 dias e pode ser reenviado em `POST /scheduled-posts/{uuid}/retry`.
 *
 * **Fuso de `scheduled_at`:** envie ISO 8601 com fuso (`2026-10-20T18:00:00-03:00` ou `...Z`); a API converte para o
 * mesmo instante em America/Sao_Paulo. Sem fuso (`2026-10-20 18:00:00`), vale o horário de America/Sao_Paulo. A resposta
 * sempre devolve ISO 8601 com o offset de São Paulo.
 *
 * **Validação por rede (422 ao criar):** cada conta é validada pelas regras da sua plataforma e, se qualquer uma falhar,
 * **nenhum** post é criado. O erro vem no formato padrão `errors.<campo>`.
 * Conta do TikTok com `can_publish` = `false` (sem a permissão `video.publish`) também dá 422, em `errors.social_account_uuids`: peça para reconectar a conta.
 * - **YouTube:** só vídeo (mp4, mov); `title` obrigatório (máx. 100 caracteres); descrição (`caption`) até 5.000 bytes
 *   (acentos e emojis ocupam mais de 1 byte).
 * - **Instagram:** vídeo mp4/mov (Reels) ou imagem JPEG (png não é aceito); `caption` até 2.200 caracteres.
 * - **TikTok:** só vídeo (mp4, mov, webm); `privacy_level` obrigatório; `caption` (vira o título no TikTok) até 2.200 caracteres.
 *   `brand_content_toggle` não pode ser `true` com `privacy_level` = SELF_ONLY. Veja as opções válidas em
 *   `GET /social-accounts/{id}/tiktok/creator-info`.
 *
 * **`error_message` de posts `failed`:** é sempre um texto fixo em pt-BR, pronto para exibir (nunca o texto cru da rede).
 * Principais mensagens:
 * - Geral: "Não foi possível publicar o post. Tente novamente em instantes."; "Assinatura inativa ou expirada no momento da publicação."; "A conta vinculada ao agendamento não foi encontrada. Reconecte a conta e tente novamente."
 * - YouTube: "A cota diária do YouTube foi esgotada. Tente novamente amanhã."; "O canal atingiu o limite de uploads do YouTube. Tente novamente mais tarde."; "O YouTube recusou o acesso à conta. Reconecte a conta YouTube e aceite todas as permissões."; "A conta Google conectada não tem um canal do YouTube. Crie o canal e agende de novo."; "Não foi possível renovar o acesso à conta do YouTube. Reconecte a conta YouTube."; "Falha ao fazer upload do vídeo para o YouTube."; "O YouTube recusou a publicação. Tente novamente em instantes."
 * - Instagram: "O acesso à conta do Instagram expirou, foi revogado ou não tem permissão para publicar. Reconecte a conta do Instagram."; "O Instagram não conseguiu processar a mídia. Verifique o formato do arquivo e tente novamente."; "Tempo esgotado: o Instagram não terminou de processar a mídia. Verifique a conta e tente novamente."; "Não foi possível se comunicar com o Instagram. Tente novamente em instantes."; "O Instagram recusou a publicação. Verifique a mídia e tente novamente em instantes."
 * - TikTok: "A conta do TikTok não tem permissão para publicar. Desconecte e reconecte a conta TikTok."; "A privacidade escolhida não está disponível para esta conta do TikTok. Escolha outra e agende de novo."; "A conta atingiu o limite de publicações do TikTok nas últimas 24 horas. Tente novamente mais tarde."; "App do TikTok não auditado: por enquanto só é possível publicar em contas privadas, com visibilidade \"somente eu\"."; "O acesso à conta do TikTok expirou ou foi revogado. Reconecte a conta TikTok."; "O TikTok não aceitou o formato do arquivo. Use um vídeo MP4, MOV ou WebM."; "O vídeo é mais longo do que o TikTok permite para esta conta."; "Tempo esgotado: o TikTok não confirmou a publicação do vídeo. Verifique a conta e tente novamente."; "Não foi possível enviar o vídeo ao TikTok. Tente novamente em instantes."
 */
class ScheduledPostController extends BaseController
{
    protected ?string $resourceClass = ScheduledPostResource::class;

    protected static string $permissionGroup = 'posts';

    protected static array $permissionMethods = [
        'view' => ['index'],
        'create' => ['store', 'uploadUrl', 'retry'],
        'delete' => ['destroy'],
    ];

    public function __construct(
        private ScheduledPostService $service,
        private StorageService $storageService,
    ) {
        parent::__construct($service);
    }

    /**
     * Gerar URL de upload
     *
     * Passo 1 do fluxo. A URL é temporária (`expires_in` segundos) e serve para **um** arquivo.
     *
     * **Permissão:** `posts.create`
     *
     * @bodyParam content_type string required Tipo do arquivo. Enum: video/mp4, video/quicktime, image/jpeg, image/png, image/jpg. Example: video/mp4
     * @bodyParam directory string required Onde guardar: `videos` ou `images` para a mídia, `thumbnails` para a capa. Enum: videos, thumbnails, images. Example: videos
     *
     * @response 200 {
     *   "url": "https://bucket.s3.amazonaws.com/workspaces/58d7.../videos/9f8e....mp4?X-Amz-Signature=...",
     *   "headers": {"Content-Type": "video/mp4"},
     *   "path": "workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/videos/9f8e7d6c-....mp4",
     *   "content_type": "video/mp4",
     *   "max_size": 32212254720,
     *   "expires_in": 3600
     * }
     *
     * @responseField path string Guarde: vai em `media_storage_path` ou `thumbnail_storage_path` no agendamento.
     * @responseField max_size integer Tamanho máximo em bytes.
     *
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.create"}
     */
    public function uploadUrl(GenerateUploadUrlRequest $request): JsonResponse
    {
        $user = $request->user();
        $access = $user->resolveCurrentAccess();

        if (! $access || ! $access->workspace) {
            return response()->json(['message' => 'Workspace não encontrado.'], 404);
        }

        $result = $this->storageService->generateUploadUrl(
            directory: $request->input('directory'),
            workspaceUuid: $access->workspace->uuid,
            contentType: $request->input('content_type'),
            extension: $request->extension(),
        );

        return response()->json($result);
    }

    /**
     * Agendar publicação
     *
     * Passo 3 do fluxo. Cria um post para cada conta em `social_account_uuids`.
     *
     * As contas sociais pertencem ao **workspace**: qualquer membro com `posts.create` agenda em qualquer conta conectada
     * nele, independente de quem a conectou. Se alguma conta for de outro workspace, a requisição falha com `no_account_linked`
     * (400) e **nenhum** post é criado (tudo ou nada).
     *
     * **Permissão:** `posts.create`
     *
     * @bodyParam media_storage_path string required `path` devolvido pelo upload (pasta `videos` ou `images`). Example: workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/videos/9f8e7d6c.mp4
     * @bodyParam thumbnail_storage_path string `path` de uma capa enviada na pasta `thumbnails`. Example: workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/thumbnails/1a2b3c.jpg
     * @bodyParam social_account_uuids string[] required UUIDs das contas (mín. 1). Qualquer conta conectada no workspace corrente, por qualquer membro. Example: ["5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f"]
     * @bodyParam title string Título (máx. 100). **Obrigatório para contas do YouTube.** Example: Meu vídeo
     * @bodyParam caption string Legenda/descrição. Limites: YouTube 5.000 bytes, Instagram 2.200 caracteres, TikTok 2.200 caracteres. Example: Descrição do vídeo
     * @bodyParam scheduled_at string Data/hora da publicação, pelo menos 5 minutos no futuro. Com fuso (ISO 8601) é convertida para America/Sao_Paulo; sem fuso, vale São Paulo. Vazio = publica agora. Example: 2026-10-20T18:00:00-03:00
     * @bodyParam is_short boolean YouTube: publicar como Short. Example: false
     * @bodyParam youtube_privacy_status string YouTube: privacidade (ver `GET /youtube-privacy-statuses`). Enum: public, private, unlisted. Example: public
     * @bodyParam youtube_category_id string YouTube: ID da categoria (ver `GET /youtube-categories`). Example: 22
     * @bodyParam youtube_tags string[] YouTube: tags (máx. 50, cada uma até 50 caracteres). Example: ["vlog", "viagem"]
     * @bodyParam youtube_made_for_kids boolean YouTube: conteúdo infantil. Example: false
     * @bodyParam privacy_level string TikTok: privacidade (**obrigatória** para contas do TikTok; use uma das `privacy_level_options` do creator-info). Enum: PUBLIC_TO_EVERYONE, MUTUAL_FOLLOW_FRIENDS, FOLLOWER_OF_CREATOR, SELF_ONLY. Example: SELF_ONLY
     * @bodyParam disable_comment boolean TikTok: desativar comentários. Example: false
     * @bodyParam disable_duet boolean TikTok: desativar dueto. Example: false
     * @bodyParam disable_stitch boolean TikTok: desativar stitch. Example: false
     * @bodyParam brand_content_toggle boolean TikTok: conteúdo de marca paga (parceria). Não pode ser `true` com `privacy_level` SELF_ONLY. Example: false
     * @bodyParam brand_organic_toggle boolean TikTok: promove a própria marca. Example: false
     * @bodyParam pipeline_card_uuid string Card do pipeline a vincular; ele vai para a etapa `scheduled`. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 201 {
     *   "message": "Vídeo(s) agendado(s) com sucesso!",
     *   "data": [
     *     {
     *       "uuid": "e1d2c3b4-...",
     *       "responsible": "Ana Souza",
     *       "media_url": "https://bucket.s3.amazonaws.com/workspaces/.../videos/abc.mp4?X-Amz-Signature=...",
     *       "total_posts_for_account": 12,
     *       "failed_posts_for_account": 1,
     *       "platform": "youtube",
     *       "title": "Meu vídeo",
     *       "caption": "Descrição do vídeo",
     *       "status": "pending",
     *       "scheduled_at": "2026-10-20T18:00:00-03:00",
     *       "published_at": null,
     *       "created_at": "2026-10-15T10:00:00-03:00",
     *       "platform_post_id": null,
     *       "platform_post_url": null,
     *       "payload": {"youtube_privacy_status": "public", "youtube_tags": ["vlog"], "is_short": false}
     *     }
     *   ]
     * }
     * @response 400 scenario="conta de outro workspace ou inexistente" {"error": "no_account_linked", "message": "A conta selecionada não está disponível neste workspace."}
     * @response 422 scenario="conta do TikTok sem permissão de publicar" {"message": "A conta do TikTok não tem permissão para publicar. Reconecte a conta do TikTok.", "errors": {"social_account_uuids": ["A conta do TikTok não tem permissão para publicar. Reconecte a conta do TikTok."]}}
     * @response 422 scenario="data muito próxima" {"message": "O agendamento precisa ser de pelo menos 5 minutos no futuro.", "errors": {"scheduled_at": ["O agendamento precisa ser de pelo menos 5 minutos no futuro."]}}
     * @response 422 scenario="YouTube sem título" {"message": "Para postar no YouTube, você precisa definir um título.", "errors": {"title": ["Para postar no YouTube, você precisa definir um título."]}}
     * @response 422 scenario="formato de mídia não aceito pela rede" {"message": "O TikTok aceita apenas vídeo (MP4, MOV ou WebM).", "errors": {"media_storage_path": ["O TikTok aceita apenas vídeo (MP4, MOV ou WebM)."]}}
     * @response 422 scenario="TikTok sem privacidade" {"message": "Escolha a privacidade do vídeo no TikTok.", "errors": {"privacy_level": ["Escolha a privacidade do vídeo no TikTok."]}}
     * @response 422 scenario="legenda longa demais" {"message": "A legenda do Instagram pode ter no máximo 2200 caracteres.", "errors": {"caption": ["A legenda do Instagram pode ter no máximo 2200 caracteres."]}}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.create"}
     */
    public function store(Request $request): JsonResponse
    {
        $formRequest = app(StoreScheduledPostRequest::class);

        $posts = $this->service->schedule(
            $formRequest->user(),
            $formRequest->validated(),
        );

        $posts->each->load('socialAccount');

        return response()->json([
            'message' => 'Vídeo(s) agendado(s) com sucesso!',
            'data' => ScheduledPostResource::collection($posts),
        ], 201);
    }

    /**
     * Cancelar agendamento
     *
     * Só posts `pending`. **Qualquer membro com `posts.delete` no workspace do post pode cancelar**, não só quem agendou (a conta é do workspace).
     * Post de outro workspace devolve 404 e nada muda. A mídia só é apagada do storage se nenhum outro post (por exemplo, o mesmo vídeo agendado em outra rede) ainda a usa.
     *
     * **Permissão:** `posts.delete`
     *
     * @urlParam scheduledPost string required UUID do post. Example: e1d2c3b4-a5f6-4789-8abc-def012345678
     *
     * @response 200 {"message": "Agendamento cancelado com sucesso."}
     * @response 409 scenario="já publicado ou em processamento" {"error": "cancel_not_allowed", "message": "Este post não pode ser cancelado pois já foi publicado ou está em processamento."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.delete"}
     */
    public function destroy(int|string $uuid): JsonResponse
    {
        $this->service->cancel($uuid);

        return response()->json([
            'message' => 'Agendamento cancelado com sucesso.',
        ]);
    }

    /**
     * Reenviar post que falhou
     *
     * Só posts `failed` do workspace corrente (**qualquer membro com `posts.create` pode reenviar**, não só quem agendou; post de outro workspace dá 404) cuja mídia ainda está guardada (`can_retry` na listagem).
     * Sem `scheduled_at` o post sai na hora; com ele, é reagendado. Título, legenda e opções não mudam, e a mídia/opções
     * **não são revalidadas** pelas regras da rede (só `scheduled_at`). Se o post falhou por token ou permissão, reconecte a conta antes de reenviar.
     * `scheduled_at` segue a mesma regra de fuso do fluxo acima (ISO com fuso é convertido para America/Sao_Paulo).
     *
     * **Permissão:** `posts.create`
     *
     * @urlParam scheduledPost string required UUID do post. Example: e1d2c3b4-a5f6-4789-8abc-def012345678
     *
     * @bodyParam scheduled_at string Nova data/hora (ISO 8601, pelo menos 5 minutos no futuro). Sem ela, reenvia agora. Example: 2026-10-25T18:00:00-03:00
     *
     * @response 200 {"message": "Post reenviado para a fila.", "data": {"uuid": "e1d2c3b4-...", "status": "pending", "can_retry": false}}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.create"}
     * @response 409 scenario="post não falhou" {"error": "retry_not_allowed", "message": "Só é possível reenviar posts que falharam."}
     * @response 422 scenario="arquivo já removido" {"error": "media_expired", "message": "O arquivo deste post não está mais disponível. Adicione o post novamente."}
     */
    public function retry(RetryScheduledPostRequest $request, string $uuid): JsonResponse
    {
        $post = $this->service->retry($uuid, $request->validated('scheduled_at'));

        return response()->json([
            'message' => 'Post reenviado para a fila.',
            'data' => new ScheduledPostResource($post),
        ]);
    }

    /**
     * Listar agendamentos
     *
     * Posts do workspace corrente, mais recentes primeiro.
     *
     * **Permissão:** `posts.view`
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "e1d2c3b4-...",
     *       "responsible": "Ana Souza",
     *       "media_url": "https://bucket.s3.amazonaws.com/workspaces/.../videos/abc.mp4?X-Amz-Signature=...",
     *       "total_posts_for_account": 12,
     *       "failed_posts_for_account": 1,
     *       "platform": "youtube",
     *       "title": "Meu vídeo",
     *       "caption": "Descrição do vídeo",
     *       "status": "pending",
     *       "scheduled_at": "2026-10-20T18:00:00-03:00",
     *       "published_at": null,
     *       "created_at": "2026-10-15T10:00:00-03:00",
     *       "platform_post_id": null,
     *       "platform_post_url": null,
     *       "payload": {"youtube_privacy_status": "public", "youtube_tags": ["vlog"], "is_short": false}
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     *
     * @responseField media_url string|null URL temporária (assinada) para exibir a mídia.
     * @responseField error_message string Só aparece quando `status` = `failed`. Texto fixo em pt-BR por rede, pronto para exibir (ver lista na descrição do grupo).
     * @responseField status string Enum: pending, processing, published, failed, cancelled.
     * @responseField platform string Enum: youtube, instagram, tiktok.
     * @responseField platform_post_url string|null Link do post publicado (YouTube e Instagram; TikTok pode vir `null`).
     * @responseField can_retry boolean `true` quando o post falhou e o arquivo ainda está guardado: mostre o botão Reenviar.
     * @responseField retry_until string|null Até quando o arquivo fica guardado para reenvio (5 dias após a falha). `null` se não dá para reenviar.
     * @responseField payload object Opções específicas da plataforma enviadas no agendamento.
     *
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }
}
