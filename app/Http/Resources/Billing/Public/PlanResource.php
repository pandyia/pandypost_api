<?php

namespace App\Http\Resources\Billing\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plano público (landing page). Expõe apenas id, name, description e os preços
 * ativos. Campos internos (is_visible, gateway_product_id, etc.) ficam no
 * Billing\Admin\PlanResource, usado pela área administrativa.
 *
 * @mixin \App\Models\Plan
 */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'prices' => PriceResource::collection($this->whenLoaded('prices')),
        ];
    }
}
