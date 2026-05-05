<?php

namespace App\Enums;

enum PipelineStage: string
{
    case IDEA      = 'idea';
    case SCRIPT    = 'script';
    case RECORDED  = 'recorded';
    case EDITING   = 'editing';
    case READY     = 'ready';
    case SCHEDULED = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::IDEA      => 'Idea',
            self::SCRIPT    => 'Script',
            self::RECORDED  => 'Recorded',
            self::EDITING   => 'Editing',
            self::READY     => 'Ready',
            self::SCHEDULED => 'Scheduled',
        };
    }

    /**
     * Returns the ordered list of stages that can be set manually by the user.
     * "Scheduled" is excluded because it is set automatically by the system.
     *
     * @return PipelineStage[]
     */
    public static function manualStages(): array
    {
        return [
            self::IDEA,
            self::SCRIPT,
            self::RECORDED,
            self::EDITING,
            self::READY,
        ];
    }

    /**
     * Determines whether a transition from the current stage to the given target is valid.
     * A user may advance one step forward or retreat one step backward.
     * The "scheduled" stage is system-only and cannot be set manually.
     */
    public function canTransitionTo(PipelineStage $target): bool
    {
        if ($target === self::SCHEDULED) {
            return false;
        }

        $order = array_values(self::manualStages());
        $currentIndex = array_search($this, $order, strict: true);
        $targetIndex  = array_search($target, $order, strict: true);

        if ($currentIndex === false || $targetIndex === false) {
            return false;
        }

        return abs($targetIndex - $currentIndex) === 1;
    }
}
