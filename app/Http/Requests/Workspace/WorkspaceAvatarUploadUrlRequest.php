<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class WorkspaceAvatarUploadUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
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
