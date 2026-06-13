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

class ContentPipelineController extends BaseController
{
    protected ?string $resourceClass = ContentPipelineResource::class;

    protected static string $permissionGroup = 'posts';
    protected static array $permissionMethods = [
        'view'   => ['index', 'show', 'board'],
        'create' => ['store', 'move'],
    ];

    public function __construct(private readonly ContentPipelineService $service)
    {
        parent::__construct($service);
    }

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
     * Creates a new pipeline card in the "idea" stage for the user's current workspace.
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
            'data'    => new ContentPipelineResource($card),
        ], 201);
    }

    /**
     * Updates editable fields (title, description, platform, due_date).
     * Stage changes must go through the dedicated move endpoint.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $formRequest     = app(UpdateContentPipelineRequest::class);
        $contentPipeline = $this->resolveCard($request);

        $this->service->update($contentPipeline, $formRequest->validated());

        return response()->json(['message' => 'Card atualizado com sucesso.']);
    }

    /**
     * Moves a pipeline card to an adjacent stage.
     * Enforces one-step transitions; rejects "scheduled" as a manual target.
     */
    public function move(Request $request): JsonResponse
    {
        $formRequest     = app(MoveStageRequest::class);
        $contentPipeline = $this->resolveCard($request);
        $targetStage     = PipelineStage::from($formRequest->validated('stage'));

        $this->service->moveStage($contentPipeline, $targetStage);

        return response()->json(['message' => 'Card movido com sucesso.']);
    }

    /**
     * Soft-deletes a pipeline card.
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->resolveCard(request())->delete();

        return response()->json(['message' => 'Card removido com sucesso.']);
    }

    /**
     * Restores a soft-deleted pipeline card.
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
}
