<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateUploadUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,image/jpeg,image/png,image/jpg'],
            'directory'    => ['required', 'string', 'in:videos,thumbnails'],
        ];
    }

    public function messages(): array
    {
        return [
            'content_type.in' => 'Tipo de arquivo não suportado. Aceitos: video/mp4, video/quicktime, image/jpeg, image/png.',
            'directory.in'    => 'Diretório inválido. Use: videos ou thumbnails.',
        ];
    }

    /**
     * Resolve a extensão do arquivo a partir do content_type informado.
     */
    public function extension(): string
    {
        return match ($this->input('content_type')) {
            'video/mp4'       => 'mp4',
            'video/quicktime' => 'mov',
            'image/jpeg'      => 'jpg',
            'image/jpg'       => 'jpg',
            'image/png'       => 'png',
            default           => 'bin',
        };
    }
}
