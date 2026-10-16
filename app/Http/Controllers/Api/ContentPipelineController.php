<?php

namespace App\Http\Controllers\Api;

use App\Enums\PipelineStage;
use App\Http\Requests\MoveStageRequest;
use App\Http\Requests\StoreContentPipelineRequest;
use App\Http\Requests\UpdateContentPipelineRequest;
use App\Http\Resources\ContentPipelineResource;
use App\Models\ContentPipeline;
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
        $formRequest = app(StoreContentPipelineRequest::class);
        $workspaceId = $request->user()->resolveCurrentAccess()->workspace_id;

        $card = $this->service->createCard(
            $request->user(),
            array_merge($formRequest->validated(), ['workspace_id' => $workspaceId])
        );

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
     * **Permissão:** nenhuma além de ser membro. ⚠️ VULN-04 (`specs/002`): a permissão ainda será definida.
     *
     * @urlParam contentPipeline string required UUID do card. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @bodyParam title string Título (máx. 255). Example: Vlog da viagem (parte 1)
     * @bodyParam description string Descrição. Example: Roteiro revisado
     * @bodyParam platform string Plataforma alvo. Enum: youtube, instagram, tiktok. Example: instagram
     * @bodyParam due_date string Data-alvo (`Y-m-d`), hoje ou futura. Example: 2026-11-05
     *
     * @response 200 {"message": "Card atualizado com sucesso."}
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $formRequest = app(UpdateContentPipelineRequest::class);
        $contentPipeline = $this->resolveCard($request);

        $this->service->update($contentPipeline, $formRequest->validated());

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
    public function move(Request $request): JsonResponse
    {
        $formRequest = app(MoveStageRequest::class);
        $contentPipeline = $this->resolveCard($request);
        $targetStage = PipelineStage::from($formRequest->validated('stage'));

        $this->service->moveStage($contentPipeline, $targetStage);

        return response()->json(['message' => 'Card movido com sucesso.']);
    }

    /**
     * Remover card
     *
     * Exclusão reversível: dá para desfazer com `restore`.
     *
     * **Permissão:** nenhuma além de ser membro. ⚠️ VULN-04 (`specs/002`): a permissão ainda será definida.
     *
     * @urlParam contentPipeline string required UUID do card. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 200 {"message": "Card removido com sucesso."}
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->resolveCard(request())->delete();

        return response()->json(['message' => 'Card removido com sucesso.']);
    }

    /**
     * Restaurar card
     *
     * Desfaz a remoção.
     *
     * **Permissão:** nenhuma além de ser membro. ⚠️ VULN-04 (`specs/002`): a permissão ainda será definida.
     *
     * @urlParam contentPipeline string required UUID do card. Example: 3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 200 {"message": "Card restaurado com sucesso."}
     */
    public function restore(int|string $id): JsonResponse
    {
        ContentPipeline::withTrashed()
            ->where('uuid', request()->route('contentPipeline'))
            ->firstOrFail()
            ->restore();

        return response()->json(['message' => 'Card restaurado com sucesso.']);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Resolves the ContentPipeline model from the route parameter UUID.
     * Needed because implicit model binding requires a type-hint in the method
     * signature, which we cannot use here due to BaseController compatibility.
     */
    private function resolveCard(Request $request): ContentPipeline
    {
        $param = $request->route('contentPipeline');

        if ($param instanceof ContentPipeline) {
            return $param;
        }

        return ContentPipeline::where('uuid', $param)->firstOrFail();
    }

    /**
     * Ver card
     *
     * ⚠️ **BUG-01:** hoje este endpoint falha (500 no PostgreSQL), ver `specs/002-security-hardening`. Não use até a correção.
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
        return parent::show($id);
    }
}
