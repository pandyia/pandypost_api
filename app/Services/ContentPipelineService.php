<?php

namespace App\Services;

use App\Enums\PipelineStage;
use App\Exceptions\ContentPipelineException;
use App\Models\ContentPipeline;
use App\Models\ScheduledPost;
use App\Models\User;
use Illuminate\Support\Collection;

class ContentPipelineService extends BaseService
{
    protected array $with = ['user'];

    public function __construct(ContentPipeline $contentPipeline)
    {
        parent::__construct($contentPipeline);
    }

    /**
     * Returns all pipeline cards for the current workspace, grouped by stage.
     * A single query is executed and grouped in memory to avoid N+1.
     */
    public function getBoard(): array
    {
        $cards = ContentPipeline::with('user', 'scheduledPost')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        $board = [];
        foreach (PipelineStage::cases() as $stage) {
            $board[$stage->value] = [];
        }

        foreach ($cards as $card) {
            $board[$card->stage->value][] = $card;
        }

        return $board;
    }

    /**
     * Moves a pipeline card to the given stage.
     * Enforces one-step-at-a-time transitions and blocks manual "scheduled" assignment.
     */
    public function moveStage(ContentPipeline $card, PipelineStage $newStage): void
    {
        if ($newStage === PipelineStage::SCHEDULED) {
            throw ContentPipelineException::scheduledStageForbidden();
        }

        if (! $card->stage->canTransitionTo($newStage)) {
            throw ContentPipelineException::invalidStageTransition($card->stage, $newStage);
        }

        $this->update($card, ['stage' => $newStage->value]);
    }

    /**
     * Links a pipeline card to a ScheduledPost and automatically moves it to "scheduled".
     * Called by ScheduledPostService after a post is successfully created.
     */
    public function markAsScheduled(ContentPipeline $card, ScheduledPost $scheduledPost): void
    {
        $this->update($card, [
            'stage'             => PipelineStage::SCHEDULED->value,
            'scheduled_post_id' => $scheduledPost->id,
        ]);
    }

    public function createCard(User $user, array $data): ContentPipeline
    {
        /** @var ContentPipeline */
        return $this->store(array_merge($data, [
            'user_id'  => $user->id,
            'stage'    => PipelineStage::IDEA->value,
        ]));
    }
}
