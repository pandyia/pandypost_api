<?php

namespace App\Http\Requests\Billing\Admin;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'required', Rule::enum(Currency::class)],
            'frequency' => ['sometimes', 'required', Rule::enum(BillingFrequency::class)],
            'trial_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.integer' => 'O valor deve ser informado em centavos (número inteiro).',
            'currency.enum' => 'A moeda selecionada é inválida.',
            'frequency.enum' => 'A frequência deve ser mensal ou anual.',
        ];
    }
}
