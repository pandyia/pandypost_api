<?php

namespace App\Contracts;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
interface OAuthProviderInterface
{
    /**
     * Retorna a URL de redirecionamento para o provedor OAuth.
     */
    public function getRedirectUrl(?User $user = null): string;

    /**
     * Sincroniza a conta após o callback do provedor OAuth.
     */
    public function syncAccount(User|Workspace $context, ?string $code = null): SocialAccount;
}
