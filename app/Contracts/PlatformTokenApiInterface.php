<?php

namespace App\Contracts;

use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;

/**
 * Renovação e revogação do token de acesso de uma conta, por rede. Cada rede aplica o próprio
 * default de expires_in e valida o que exige (refresh_token); quem grava no banco é o SocialAccount.
 */
interface PlatformTokenApiInterface
{
    /**
     * @return array{access_token: string, expires_in: int, refresh_token?: string}
     *
     * @throws SocialAccountException
     */
    public function refreshAccountToken(SocialAccount $account): array;

    public function revokeAccountToken(SocialAccount $account): void;
}
