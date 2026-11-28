<?php

namespace App\Http\Requests\Billing\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ChangeSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A existência/atividade do preço é resolvida no service (inexistente ou inativo = 404).
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'price.required' => 'O preço é obrigatório.',
            'price.uuid' => 'O preço informado é inválido.',
        ];
    }
}
