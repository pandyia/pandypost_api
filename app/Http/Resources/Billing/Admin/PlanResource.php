<?php

namespace App\Http\Resources\Billing\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Plan */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'is_visible' => $this->is_visible,
            'is_active' => $this->is_active,
            'gateway_product_id' => $this->gateway_product_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'prices' => PriceResource::collection($this->whenLoaded('prices')),
        ];
    }
}
