<?php

namespace App\Jobs\Concerns;

use DateTimeInterface;

// Consulta a plataforma com espera crescente até um prazo. A classe define MONITORING_WINDOW_MINUTES e POLL_DELAYS_SECONDS.
trait PollsPlatformStatus
{
    // Prazo total do acompanhamento, contado do despacho do job.
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(static::MONITORING_WINDOW_MINUTES);
    }

    // Espera antes de tentar de novo depois de um erro na consulta.
    public function backoff(): array
    {
        return static::POLL_DELAYS_SECONDS;
    }

    // Espera até a próxima consulta: cresce a cada tentativa e para no último valor.
    private function nextPollDelay(): int
    {
        $delays = static::POLL_DELAYS_SECONDS;

        return $delays[min($this->attempts() - 1, count($delays) - 1)];
    }
}
