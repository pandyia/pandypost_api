<?php

namespace App\Http\Requests\Billing\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'regex:/^cs_[A-Za-z0-9_]+$/', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_id.required' => 'O identificador da sessão de checkout é obrigatório.',
            'session_id.regex' => 'O identificador da sessão de checkout é inválido.',
        ];
    }
}
