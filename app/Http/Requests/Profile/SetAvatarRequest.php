<?php

namespace App\Http\Requests\Profile;

use App\Rules\AvatarPathRule;
use Illuminate\Foundation\Http\FormRequest;

class SetAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', new AvatarPathRule($this->user()->uuid)],
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'Informe o caminho da foto enviada.',
        ];
    }
}
