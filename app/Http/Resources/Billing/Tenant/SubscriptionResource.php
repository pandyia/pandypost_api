<?php

namespace App\Http\Resources\Billing\Tenant;

use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = Price::withTrashed()->with('plan')
            ->where('gateway_price_id', $this->stripe_price)
            ->first();

        return [
            'billing_status' => $this->billing_status,
            'stripe_status' => $this->stripe_status,
            'on_trial' => $this->onTrial(),
            'trial_ends_at' => $this->trial_ends_at,
            'ends_at' => $this->ends_at,
            'plan' => $price?->plan ? [
                'id' => $price->plan->uuid,
                'name' => $price->plan->name,
            ] : null,
            'price' => $price ? [
                'id' => $price->uuid,
                'amount' => $price->amount,
                'currency' => $price->currency->value,
                'frequency' => $price->frequency->value,
            ] : null,
        ];
    }
}
