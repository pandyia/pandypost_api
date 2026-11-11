<?php

namespace App\Services\Analytics;

use App\Contracts\AnalyticsProviderInterface;
use App\Enums\Platform;
use App\Exceptions\AnalyticsApiException;
use App\Exceptions\AnalyticsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Base comum dos providers de analytics (spec 022, D7): client HTTP, GET com
 * conferência e o esqueleto do tratamento de erro. A detecção de sucesso e de
 * erro de reautenticação tem semântica própria em cada rede e fica no filho.
 */
abstract class AbstractAnalyticsProvider implements AnalyticsProviderInterface
{
    abstract protected function platform(): Platform;

    /** Erro que exige reconectar a conta (cada rede sinaliza do seu jeito). */
    abstract protected function isReauthError(Response $response): bool;

    /** Sucesso pelo status HTTP; o TikTok sobrescreve (200 com error.code é falha). */
    protected function isSuccessful(Response $response): bool
    {
        return $response->successful();
    }

    protected function client(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(20);
    }

    protected function get(PendingRequest $client, string $url, array $query): array
    {
        $response = $client->get($url, $query);
        $this->ensureSuccessful($response);

        return $response->json() ?? [];
    }

    protected function ensureSuccessful(Response $response): void
    {
        if ($this->isSuccessful($response)) {
            return;
        }

        if ($this->isReauthError($response)) {
            throw AnalyticsException::reauthRequired($this->platform());
        }

        throw AnalyticsApiException::unexpectedResponse($this->platform()->label(), $response->status(), $response->body());
    }
}
