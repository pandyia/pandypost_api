<?php

namespace App\Contracts;

use App\Models\ScheduledPost;
use App\Models\SocialAccount;

interface SocialMediaServiceInterface
{
    /**
     * O contrato sagrado de polimorfismo para qualquer rede social.
     * Toda classe que implementar essa interface é obrigada a ter o método upload().
     *
     * @param SocialAccount $account Conta do usuário logada via OAuth
     * @param ScheduledPost $post O post agendado contendo o media_path do disco
     * @return void
     */
    public function upload(SocialAccount $account, ScheduledPost $post): void;
}
