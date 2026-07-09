<?php

namespace App\Http\Requests\Billing\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price' => [
                'required',
                Rule::exists('prices', 'uuid')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'price.required' => 'O preço é obrigatório.',
            'price.exists' => 'O preço selecionado é inválido ou está inativo.',
        ];
    }
}
