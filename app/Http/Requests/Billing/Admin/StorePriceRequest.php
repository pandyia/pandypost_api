<?php

namespace App\Http\Requests\Billing\Admin;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'frequency' => ['required', Rule::enum(BillingFrequency::class)],
            'trial_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'O valor é obrigatório.',
            'amount.integer' => 'O valor deve ser informado em centavos (número inteiro).',
            'currency.required' => 'A moeda é obrigatória.',
            'currency.enum' => 'A moeda selecionada é inválida.',
            'frequency.required' => 'A frequência é obrigatória.',
            'frequency.enum' => 'A frequência deve ser mensal ou anual.',
        ];
    }
}
