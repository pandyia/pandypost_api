<?php

namespace App\Http\Resources\Billing\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Preço público (landing page). Expõe apenas o necessário para exibir o valor,
 * sem campos internos (is_active, timestamps). A formatação do
 * valor e os labels de currency/frequency ficam a cargo do front (i18n).
 *
 * @mixin \App\Models\Price
 */
class PriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'amount' => $this->amount, // centavos
            'currency' => $this->currency->value,
            'frequency' => $this->frequency->value,
            'trial_period_days' => $this->trial_period_days,
        ];
    }
}
