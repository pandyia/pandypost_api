<?php

namespace App\Http\Resources\Billing\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PriceHistory */
class PriceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'frequency' => $this->frequency->value,
            'trial_period_days' => $this->trial_period_days,
            'reason' => $this->reason,
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
        ];
    }
}
