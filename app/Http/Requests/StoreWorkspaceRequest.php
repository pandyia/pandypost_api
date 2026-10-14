<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Só o nome é editável pelo usuário: is_personal_team, uuid, perfis e
 * permissões nunca vêm do request (specs/002 VULN-05).
 */
class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do workspace é obrigatório.',
            'name.max' => 'O nome do workspace deve ter no máximo 255 caracteres.',
        ];
    }
}
