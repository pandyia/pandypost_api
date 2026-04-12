<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role_uuid' => ['required', 'exists:roles,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Informe um email válido.',
            'email.max' => 'O email deve ter no máximo 255 caracteres.',
            'role_uuid.required' => 'O cargo é obrigatório.',
            'role_uuid.exists' => 'O cargo informado não existe.',
        ];
    }
}
