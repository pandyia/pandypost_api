<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Exceptions\SocialAccountException;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OAuthStateService;

abstract class AbstractOAuthProvider implements OAuthProviderInterface
{
    /** O callback é público: workspace, iniciador, plataforma e nonce de uso único viajam cifrados no state. */
    protected function stateFor(?User $user, string $platform): string
    {
        $workspace = $user?->currentAccess?->workspace;

        if (! $workspace) {
            throw SocialAccountException::oauthInitializationFailed();
        }

        return app(OAuthStateService::class)->issue($user, $workspace, $platform);
    }

    /**
     * O workspace é o do state (callback) ou, sem ele, o corrente do usuário. A conta pertence ao
     * workspace; o user_id é só quem conectou por último (spec 017).
     *
     * @return array{0: int, 1: int} [workspace_id, user_id]
     */
    protected function ownerIds(User $user, ?Workspace $workspace = null): array
    {
        return [$workspace?->id ?? $user->currentAccess->workspace_id, $user->id];
    }
}
