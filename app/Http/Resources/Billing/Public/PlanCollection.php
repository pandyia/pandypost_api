<?php

namespace App\Http\Resources\Billing\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Coleção pública de planos (landing page).
 *
 * @mixin \Illuminate\Support\Collection<int, \App\Models\Plan>
 */
class PlanCollection extends ResourceCollection
{
    public $collects = PlanResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
            ],
        ];
    }
}
