<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Preferências de notificação do usuário (`$this->resource` é o array por família): campos soltos = e-mail, `in_app` = aviso no app.
 */
class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'invite' => $this->resource['invite'],
            'posts' => $this->resource['posts'],
            'social_accounts' => $this->resource['social_accounts'],
            'post_published' => $this->resource['post_published'],
            'in_app' => $this->resource['in_app'],
        ];
    }
}
