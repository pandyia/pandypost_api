<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/\d/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Informe a senha atual.',
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.different' => 'A nova senha precisa ser diferente da atual.',
            'password.min' => 'A nova senha precisa ter pelo menos 8 caracteres.',
            'password.regex' => 'A nova senha precisa ter pelo menos uma letra maiúscula e um número.',
        ];
    }
}
