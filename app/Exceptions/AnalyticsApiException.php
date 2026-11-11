<?php

namespace App\Exceptions;

use App\Enums\Exceptions\AnalyticsError;

/**
 * Resposta inesperada da API de analytics de uma rede. De propósito FORA da
 * hierarquia de AnalyticsException: o AnalyticsService::callProvider relança
 * AnalyticsException ao cliente e trata o resto como inesperado (log com a
 * mensagem + 502 providerUnavailable) — contrato e log idênticos aos do
 * RuntimeException que esta classe substitui (spec 022, D6).
 */
class AnalyticsApiException extends BaseException
{
    /** A mensagem carrega status e corpo de terceiro e vai SÓ para o log (Q4/A3 da spec 022). */
    public static function unexpectedResponse(string $platform, int $status, string $body): self
    {
        return static::make(AnalyticsError::UNEXPECTED_API_RESPONSE, "{$platform} API respondeu {$status}: {$body}");
    }
}
