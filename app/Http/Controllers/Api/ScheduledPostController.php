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
     * **Permissão:** `posts.create`
     *
     * @bodyParam media_storage_path string required `path` devolvido pelo upload (pasta `videos` ou `images`). Example: workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/videos/9f8e7d6c.mp4
     * @bodyParam thumbnail_storage_path string `path` de uma capa enviada na pasta `thumbnails`. Example: workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/thumbnails/1a2b3c.jpg
     * @bodyParam social_account_uuids string[] required UUIDs das contas (mín. 1). Só contas conectadas pelo próprio usuário. Example: ["5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f"]
     * @bodyParam title string Título (máx. 100). Example: Meu vídeo
     * @bodyParam caption string Legenda/descrição. Example: Descrição do vídeo
     * @bodyParam scheduled_at string Data/hora da publicação, pelo menos 5 minutos no futuro. Vazio = publica agora. Example: 2026-10-20T18:00:00-03:00
     * @bodyParam is_short boolean YouTube: publicar como Short. Example: false
     * @bodyParam youtube_privacy_status string YouTube: privacidade (ver `GET /youtube-privacy-statuses`). Enum: public, private, unlisted. Example: public
     * @bodyParam youtube_category_id string YouTube: ID da categoria (ver `GET /youtube-categories`). Example: 22
     * @bodyParam youtube_tags string[] YouTube: tags (máx. 50, cada uma até 50 caracteres). Example: ["vlog", "viagem"]
     * @bodyParam youtube_made_for_kids boolean YouTube: conteúdo infantil. Example: false
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
     * @response 400 scenario="conta não é do usuário" {"error": "no_account_linked", "message": "..."}
     * @response 422 scenario="validação" {"message": "O agendamento precisa ser de pelo menos 5 minutos no futuro.", "errors": {"scheduled_at": ["O agendamento precisa ser de pelo menos 5 minutos no futuro."]}}
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
     * Só posts `pending` criados pelo próprio usuário. A mídia é apagada do storage.
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
        $this->service->cancel(
            request()->user(),
            $uuid,
        );

        return response()->json([
            'message' => 'Agendamento cancelado com sucesso.',
        ]);
    }

    /**
     * Reenviar post que falhou
     *
     * Só posts `failed` do workspace corrente cuja mídia ainda está guardada (`can_retry` na listagem).
     * Sem `scheduled_at` o post sai na hora; com ele, é reagendado. Título, legenda e opções não mudam.
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
     * @responseField platform_post_url string|null Link do post publicado (YouTube e Instagram).
     * @responseField error_message string Só aparece quando `status` = `failed`.
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
