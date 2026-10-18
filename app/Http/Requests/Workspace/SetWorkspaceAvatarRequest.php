<?php

namespace App\Http\Requests\Workspace;

use App\Rules\WorkspaceAvatarPathRule;
use Illuminate\Foundation\Http\FormRequest;

class SetWorkspaceAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', new WorkspaceAvatarPathRule($this->route('workspace')->uuid)],
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'Informe o caminho da foto enviada.',
        ];
    }
}
