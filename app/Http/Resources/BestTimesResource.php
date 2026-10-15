<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Melhores horários calculados pelo MetricsCalculator (contrato em specs/003).
 */
class BestTimesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
