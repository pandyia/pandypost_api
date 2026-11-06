<?php

namespace App\Services;

use App\Exceptions\SocialAccountException;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * `state` do OAuth (spec 017): payload cifrado com workspace, iniciador, nonce e expiração.
 * O nonce é de uso único (consumido no cache), então replay, expirado ou adulterado é recusado.
 */
class OAuthStateService
{
    public const TTL_MINUTES = 10;

    private const CACHE_PREFIX = 'oauth_state:';

    public function issue(User $user, Workspace $workspace, string $platform): string
    {
        $nonce = Str::random(40);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put(self::CACHE_PREFIX.$nonce, [
            'workspace_uuid' => $workspace->uuid,
            'user_id' => $user->id,
            'platform' => $platform,
        ], $expiresAt);

        return encrypt([
            'workspace_uuid' => $workspace->uuid,
            'user_id' => $user->id,
            'platform' => $platform,
            'nonce' => $nonce,
            'exp' => $expiresAt->getTimestamp(),
        ]);
    }

    /**
     * Valida e consome o state. Qualquer falha vira a mesma exception, sem detalhe do motivo.
     * O state só vale para a plataforma para a qual foi emitido (a do `{platform}` do callback).
     *
     * @return array{workspace_uuid: string, user_id: int, platform: string}
     */
    public function consume(?string $state, string $platform): array
    {
        if (! $state) {
            throw SocialAccountException::invalidOAuthState();
        }

        try {
            $payload = decrypt($state);
        } catch (Throwable) {
            throw SocialAccountException::invalidOAuthState();
        }

        if (! is_array($payload)
            || ! is_string($payload['nonce'] ?? null) || $payload['nonce'] === ''
            || ! is_string($payload['workspace_uuid'] ?? null)
            || ! is_string($payload['platform'] ?? null)
            || ! is_int($payload['user_id'] ?? null)
            || ! is_int($payload['exp'] ?? null)) {
            throw SocialAccountException::invalidOAuthState();
        }

        // Consome antes de validar o resto: o state é de uso único mesmo quando falha.
        $stored = Cache::pull(self::CACHE_PREFIX.$payload['nonce']);

        if ($payload['exp'] < now()->getTimestamp()
            || ! is_array($stored)
            || $stored['workspace_uuid'] !== $payload['workspace_uuid']
            || $stored['user_id'] !== $payload['user_id']
            || ($stored['platform'] ?? null) !== $payload['platform']
            || $payload['platform'] !== $platform) {
            throw SocialAccountException::invalidOAuthState();
        }

        return ['workspace_uuid' => $payload['workspace_uuid'], 'user_id' => $payload['user_id'], 'platform' => $payload['platform']];
    }
}
