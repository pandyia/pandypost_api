<?php

namespace App\Http\Requests\Workspace;

use App\Services\WorkspaceService;
use Illuminate\Foundation\Http\FormRequest;

class WorkspaceAvatarUploadUrlRequest extends FormRequest
{
    /** A permissão no workspace da URL vem antes da validação do corpo (403 antes de 422). */
    public function authorize(WorkspaceService $service): bool
    {
        $service->authorizePermissionInWorkspace($this->user(), $this->route('workspace')->uuid, 'workspaces.update');

        return true;
    }

    public function rules(): array
    {
        return [
            'content_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'content_type.required' => 'Informe o tipo da imagem.',
            'content_type.in' => 'Use uma imagem JPG, PNG ou WebP.',
        ];
    }
}
