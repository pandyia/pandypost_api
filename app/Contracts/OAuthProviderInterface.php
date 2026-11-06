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
     * Sincroniza a conta após o callback do provedor OAuth. A conta fica no workspace informado
     * (o do state) ou no corrente do usuário; `user_id` = quem conectou (spec 017).
     */
    public function syncAccount(User $user, ?string $code = null, ?Workspace $workspace = null): SocialAccount;
}
