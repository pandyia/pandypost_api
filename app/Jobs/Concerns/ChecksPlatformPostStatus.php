<?php

namespace App\Jobs\Concerns;

use App\Enums\ScheduledPostStatus;
use App\Exceptions\BaseException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Esqueleto comum dos jobs de conferência de publicação (TikTok e Instagram):
 * mesmas tentativas, mesmo backoff e mesmo desfecho quando elas se esgotam.
 * O corpo de markFailed é de cada job (o TikTok confere estado final antes de
 * gravar; o Instagram não) — unificá-los mudaria comportamento (spec 022, D8).
 */
trait ChecksPlatformPostStatus
{
    public int $tries = 15;

    /** Marca o post como falho com a mensagem fixa da rede. */
    abstract protected function markFailed(string $message): void;

    /** Exceção de timeout da rede, usada quando as tentativas se esgotam sem estado final. */
    abstract protected function timeoutException(): BaseException;

    /** Texto do log quando a rede ainda está processando (mantém o texto atual de cada job). */
    abstract protected function stillProcessingLogMessage(int $delay): string;

    public function backoff(): array
    {
        return [2, 4, 8, 16, 30];
    }

    /** Esgotou as tentativas (ou erro inesperado): sem estado final, o post não pode ficar em processing para sempre. */
    public function failed(Throwable $exception): void
    {
        $this->post->refresh();

        if ($this->post->status !== ScheduledPostStatus::PROCESSING->value) {
            return;
        }

        $this->markFailed($exception instanceof BaseException
            ? $exception->getMessage()
            : $this->timeoutException()->getMessage());
    }

    private function retryWithBackoff(): void
    {
        $backoffValues = $this->backoff();
        $attempt = min($this->attempts() - 1, count($backoffValues) - 1);
        $delay = $backoffValues[$attempt] ?? 30;

        Log::info($this->stillProcessingLogMessage($delay));
        $this->release($delay);
    }
}
