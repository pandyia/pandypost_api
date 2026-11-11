<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Só o perfil é editável: user_id e workspace_id nunca vêm do request (specs/002 VULN-01).
 */
class UpdateAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()->resolveCurrentAccess()?->workspace_id;

        return [
            // O perfil precisa ser do workspace corrente (specs/002 VULN-02).
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('workspace_id', $workspaceId)],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.required' => 'O perfil é obrigatório.',
            'role_id.exists' => 'O perfil informado não existe neste workspace.',
        ];
    }
}
