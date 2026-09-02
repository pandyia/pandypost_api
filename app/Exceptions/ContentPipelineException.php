<?php

namespace App\Exceptions;

use App\Enums\Exceptions\PipelineError;
use App\Enums\PipelineStage;

class ContentPipelineException extends BaseException
{
    public static function invalidStageTransition(PipelineStage $from, PipelineStage $to): self
    {
        $error   = PipelineError::INVALID_STAGE_TRANSITION;
        $context = "não é possível mover de '{$from->value}' para '{$to->value}'";

        return static::make($error, $error->message($context));
    }

    public static function scheduledStageForbidden(): self
    {
        return static::make(PipelineError::SCHEDULED_STAGE_FORBIDDEN);
    }

    public static function notFound(): self
    {
        return static::make(PipelineError::CARD_NOT_FOUND);
    }
}
