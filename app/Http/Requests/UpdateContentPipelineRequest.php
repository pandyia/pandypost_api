<?php

namespace App\Http\Requests;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'platform'    => ['sometimes', 'nullable', Rule::enum(Platform::class)],
            'due_date'    => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.max'               => 'O título não pode ter mais de 255 caracteres.',
            'platform.enum'           => 'A plataforma deve ser uma das opções: youtube, instagram, tiktok.',
            'due_date.after_or_equal' => 'A data-alvo deve ser hoje ou uma data futura.',
        ];
    }
}
