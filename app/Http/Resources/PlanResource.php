<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => "R$ " . number_format($this->price, 2, ',', '.'),
            'limits' => [
                'posts' => $this->monthly_posts_limit,
                'accounts' => $this->social_accounts_limit,
            ],
            'is_active' => (bool) $this->is_active
        ];
    }
}