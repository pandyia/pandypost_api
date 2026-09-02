<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

class TikTokOAuthProvider implements OAuthProviderInterface
{
    public function getRedirectUrl(?User $user = null): string
    {
        $workspace = $user?->currentAccess?->workspace;
        $state = $workspace ? encrypt($workspace->uuid) : '';

        // Gera o callback simulando o retorno do TikTok com sucesso
        $frontendCallbackUrl = config('app.frontend_url') . '/social-accounts/callback';
        
        // Em vez de ir ao TikTok real, vamos direto para o callback do nosso backend, 
        // o qual por sua vez redireciona para o frontend com ?success=1
        return url("api/social-accounts/tiktok/callback?state=" . urlencode($state) . "&code=mock_tiktok_code");
    }

    public function syncAccount(User|Workspace $context, ?string $code = null): SocialAccount
    {
        if ($context instanceof Workspace) {
            $workspaceId = $context->id;
            $userId = $context->accesses()->first()?->user_id;
        } else {
            $workspaceId = $context->currentAccess->workspace_id;
            $userId = $context->id;
        }

        // Cria ou atualiza a conta social de simulação para desenvolvimento
        return SocialAccount::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'platform' => 'tiktok',
                'platform_id' => 'tiktok_stub_' . uniqid(),
            ],
            [
                'user_id' => $userId,
                'access_token' => 'mock_tiktok_access_token_' . uniqid(),
                'refresh_token' => 'mock_tiktok_refresh_token_' . uniqid(),
                'expires_at' => now()->addDays(60),
                'nickname' => 'TikTok Stub User',
                'avatar' => null,
            ]
        );
    }
}
