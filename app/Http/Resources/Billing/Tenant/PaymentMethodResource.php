<?php

namespace App\Http\Resources\Billing\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recebe ['method' => Laravel\Cashier\PaymentMethod, 'is_default' => bool].
 * Só expõe dados não sensíveis (brand/last4/exp) — nunca o número do cartão.
 */
class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $method = $this->resource['method'];
        $card = $method->card;

        return [
            'id' => $method->id,
            'brand' => $card->brand ?? null,
            'last_digits' => $card->last4 ?? null,
            'exp_month' => $card->exp_month ?? null,
            'exp_year' => $card->exp_year ?? null,
            'is_default' => $this->resource['is_default'],
        ];
    }
}
