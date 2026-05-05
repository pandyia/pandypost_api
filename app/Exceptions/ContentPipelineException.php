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

        return new self($error->message($context), $error, $error->httpCode());
    }

    public static function scheduledStageForbidden(): self
    {
        $error = PipelineError::SCHEDULED_STAGE_FORBIDDEN;

        return new self($error->message(), $error, $error->httpCode());
    }

    public static function notFound(): self
    {
        $error = PipelineError::CARD_NOT_FOUND;

        return new self($error->message(), $error, $error->httpCode());
    }
}
