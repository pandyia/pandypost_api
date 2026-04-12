<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                Rule::exists('plans', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'O plano é obrigatório.',
            'plan_id.exists' => 'O plano selecionado é inválido ou está desativado.',
        ];
    }
}
