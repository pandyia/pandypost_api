<?php

namespace App\Http\Resources\Billing\Tenant;

use App\Models\Price;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = Price::withTrashed()->with('plan')
            ->where('gateway_price_id', $this->stripe_price)
            ->first();

        $change = $this->pendingChange;

        return [
            'billing_status' => $this->billing_status,
            'stripe_status' => $this->stripe_status,
            'on_trial' => $this->onTrial(),
            'trial_ends_at' => $this->trial_ends_at,
            'ends_at' => $this->ends_at,
            'next_billing_at' => $this->nextBillingAt(),
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
            'pending_change' => $change ? [
                'price' => [
                    'id' => $change->price->uuid,
                    'amount' => $change->price->amount,
                    'currency' => $change->price->currency->value,
                    'frequency' => $change->price->frequency->value,
                ],
                'plan' => [
                    'id' => $change->price->plan?->uuid,
                    'name' => $change->price->plan?->name,
                ],
                'effective_at' => $change->effective_at,
            ] : null,
        ];
    }
}
