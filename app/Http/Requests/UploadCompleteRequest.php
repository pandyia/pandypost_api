<?php

namespace App\Http\Requests;

use App\Rules\StoragePathRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceUuid = $this->user()?->resolveCurrentAccess()?->workspace?->uuid ?? '';

        return [
            // Formato e prefixo do workspace aqui; a existência no S3 é conferida pelo service.
            'media_storage_path' => ['required', 'string', new StoragePathRule($workspaceUuid, ['videos', 'images'], false)],
        ];
    }

    public function messages(): array
    {
        return [
            'media_storage_path.required' => 'Informe o caminho do vídeo enviado.',
        ];
    }
}
