<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Preferências de e-mail do usuário (`$this->resource` é o array por família).
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
        ];
    }
}
