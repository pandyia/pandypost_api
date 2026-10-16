<?php

namespace App\Http\Resources;

use App\Enums\LandingBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property LandingBrand $resource
 */
class LandingSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'brand' => $this->resource->value,
        ];
    }
}
