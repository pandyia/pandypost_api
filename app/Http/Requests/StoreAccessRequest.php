<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()->resolveCurrentAccess()?->workspace_id;

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            // O perfil precisa ser do workspace corrente (specs/002 VULN-02).
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('workspace_id', $workspaceId)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O usuário é obrigatório.',
            'user_id.exists' => 'O usuário informado não existe.',
            'role_id.required' => 'O perfil é obrigatório.',
            'role_id.exists' => 'O perfil informado não existe neste workspace.',
        ];
    }
}
