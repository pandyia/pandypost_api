<?php

namespace App\Http\Controllers\Api;

use App\Enums\PipelineStage;
use App\Http\Requests\MoveStageRequest;
use App\Http\Requests\StoreContentPipelineRequest;
use App\Http\Requests\UpdateContentPipelineRequest;
use App\Http\Resources\ContentPipelineResource;
use App\Services\ContentPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Pipeline de conteúdo
 *
 * Kanban de produção de conteúdo do workspace corrente.
 *
 * Etapas, em ordem: `idea` → `script` → `recorded` → `editing` → `ready` → `scheduled`.
 * O card nasce em `idea` e anda **uma etapa por vez** (para frente ou para trás).
 * `scheduled` é automática: o card chega lá quando um agendamento é criado com `pipeline_card_uuid`.
 */
class ContentPipelineController extends BaseController
{
    protected ?string $resourceClass = ContentPipelineResource::class;

    protected static string $permissionGroup = 'posts';

    protected static array $permissionMethods = [
        'view' => ['index', 'show', 'board'],
        'create' => ['store', 'move'],
        'update' => ['update'],
        'delete' => ['destroy', 'restore'],
    ];

    protected static array $formRequests = [
        'store' => StoreContentPipelineRequest::class,
        'update' => UpdateContentPipelineRequest::class,
    ];

    public function __construct(private readonly ContentPipelineService $service)
    {
        parent::__construct($service);
    }

    /**
     * Quadro
     *
     * Todos os cards agrupados por etapa. Toda etapa aparece, mesmo vazia. Em cada coluna: data-alvo mais próxima primeiro.
     *
     * **Permissão:** `posts.view`
     *
     * @response 200 {
     *   "idea": [{
     *     "uuid": "3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f",
     *     "title": "Vlog da viagem",
     *     "description": "Roteiro em anexo",
     *     "stage": "idea",
     *     "platform": "youtube",
     *     "due_date": "2026-10-30",
     *     "created_by": "Ana Souza",
     *     "scheduled_post_uuid": null,
     *     "created_at": "2026-10-15T10:00:00-03:00",
     *     "updated_at": "2026-10-15T10:00:00-03:00"
     *   }],
     *   "script": [],
     *   "recorded": [],
     *   "editing": [],
     *   "ready": [],
     *   "scheduled": []
     * }
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.view"}
     */
    public function board(Request $request): JsonResponse
    {
        $board = $this->service->getBoard();

        $formatted = [];
        foreach ($board as $stage => $cards) {
            $formatted[$stage] = ContentPipelineResource::collection($cards);
        }

        return response()->json($formatted);
    }

    /**
     * Criar card
     *
     * Sempre começa em `idea`.
     *
     * **Permissão:** `posts.create`
     *
     * @bodyParam title string required Título (máx. 255). Example: Vlog da viagem
     * @bodyParam description string Descrição livre. Example: Roteiro em anexo
     * @bodyParam platform string Plataforma alvo. Enum: youtube, instagram, tiktok. Example: youtube
     * @bodyParam due_date string Data-alvo (`Y-m-d`), hoje ou futura. Example: 2026-10-30
     *
     * @response 201 {"message": "Card criado com sucesso.", "data": {
     *     "uuid": "3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f",
     *     "title": "Vlog da viagem",
     *     "description": "Roteiro em anexo",
     *     "stage": "idea",
     *     "platform": "youtube",
     *     "due_date": "2026-10-30",
     *     "created_by": "Ana Souza",
     *     "scheduled_post_uuid": null,
     *     "created_at": "2026-10-15T10:00:00-03:00",
     *     "updated_at": "2026-10-15T10:00:00-03:00"
     *   }}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.create"}
     */
    public function store(Request $request): JsonResponse
    {
        $card = $this->service->createCard($request->user(), $this->validatedInput('store'));

        $card->load('user');

        return response()->json([
            'message' => 'Card criado com sucesso.',
            'data' => new ContentPipelineResource($card),
        ], 201);
    }

    /**
     * Editar card
     *
     * Envie só os campos que mudam. Para mudar de etapa use `move`.
     *
     * **Permissão:** `posts.update`
     *
     * @urlParam contentPipeline string required UUID do card. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @bodyParam title string Título (máx. 255). Example: Vlog da viagem (parte 1)
     * @bodyParam description string Descrição. Example: Roteiro revisado
     * @bodyParam platform string Plataforma alvo. Enum: youtube, instagram, tiktok. Example: instagram
     * @bodyParam due_date string Data-alvo (`Y-m-d`), hoje ou futura. Example: 2026-11-05
     *
     * @response 200 {"message": "Card atualizado com sucesso."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.update"}
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $data = $this->validatedInput('update');
        $contentPipeline = $this->service->findByUuid($request->route('contentPipeline'));

        $this->service->update($contentPipeline, $data);

        return response()->json(['message' => 'Card atualizado com sucesso.']);
    }

    /**
     * Mover card de etapa
     *
     * Só para a etapa vizinha (anterior ou seguinte). `scheduled` não é aceita aqui.
     *
     * **Permissão:** `posts.create`
     *
     * @urlParam contentPipeline string required UUID do card. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @bodyParam stage string required Etapa de destino. Enum: idea, script, recorded, editing, ready. Example: script
     *
     * @response 200 {"message": "Card movido com sucesso."}
     * @response 422 scenario="pulou etapa" {"error": "invalid_stage_transition", "message": "Transição de etapa inválida: idea → editing."}
     * @response 422 scenario="destino scheduled" {"error": "scheduled_stage_forbidden", "message": "A etapa 'scheduled' não pode ser definida manualmente. Ela é atribuída automaticamente ao agendar um post."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.create"}
     */
    public function move(MoveStageRequest $request): JsonResponse
    {
        $contentPipeline = $this->service->findByUuid($request->route('contentPipeline'));
        $targetStage = PipelineStage::from($request->validated('stage'));

        $this->service->moveStage($contentPipeline, $targetStage);

        return response()->json(['message' => 'Card movido com sucesso.']);
    }

    /**
     * Remover card
     *
     * Exclusão reversível: dá para desfazer com `restore`.
     *
     * **Permissão:** `posts.delete`
     *
     * @urlParam contentPipeline string required UUID do card. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 200 {"message": "Card removido com sucesso."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.delete"}
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->service->destroyByUuid(request()->route('contentPipeline'));

        return response()->json(['message' => 'Card removido com sucesso.']);
    }

    /**
     * Restaurar card
     *
     * Desfaz a remoção.
     *
     * **Permissão:** `posts.delete`
     *
     * @urlParam contentPipeline string required UUID do card. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 200 {"message": "Card restaurado com sucesso."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "posts.delete"}
     */
    public function restore(int|string $id): JsonResponse
    {
        $this->service->restoreByUuid(request()->route('contentPipeline'));

        return response()->json(['message' => 'Card restaurado com sucesso.']);
    }

    /**
     * Ver card
     *
     * **Permissão:** `posts.view`
     *
     * @urlParam contentPipeline string required UUID do card. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 200 {"data": {
     *     "uuid": "3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f",
     *     "title": "Vlog da viagem",
     *     "description": "Roteiro em anexo",
     *     "stage": "idea",
     *     "platform": "youtube",
     *     "due_date": "2026-10-30",
     *     "created_by": "Ana Souza",
     *     "scheduled_post_uuid": null,
     *     "created_at": "2026-10-15T10:00:00-03:00",
     *     "updated_at": "2026-10-15T10:00:00-03:00"
     *   }}
     */
    public function show(int|string $id): Response
    {
        $contentPipeline = $this->service->findByUuid(request()->route('contentPipeline'));

        return (new ContentPipelineResource($contentPipeline))->response();
    }
}
