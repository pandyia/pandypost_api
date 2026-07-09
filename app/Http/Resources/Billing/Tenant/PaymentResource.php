<?php

namespace App\Http\Resources\Billing\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'method' => $this->method,
            'amount' => $this->amount,
            'amount_formatted' => $this->formattedAmount(),
            'currency' => $this->currency?->value,
            'paid_at' => $this->paid_at,
            'due_date' => $this->due_date,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            // Download direto no Stripe (URLs hospedadas)
            'hosted_invoice_url' => $this->gateway_hosted_invoice_url,
            'invoice_pdf' => $this->gateway_invoice_pdf,
            'receipt_url' => $this->receipt_url,
            'created_at' => $this->created_at,
        ];
    }

    private function formattedAmount(): string
    {
        $symbol = $this->currency?->symbol() ?? '';

        return trim($symbol . ' ' . number_format($this->amount / 100, 2, ',', '.'));
    }
}
