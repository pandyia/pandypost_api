<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_uuid' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_uuid.required' => 'Informe o perfil do usuário.',
            'role_uuid.uuid' => 'O perfil informado é inválido.',
        ];
    }
}
