<?php

namespace App\Http\Requests\Admin;

use App\Enums\LandingBrand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', Rule::enum(LandingBrand::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'brand.required' => 'Escolha um tema para a landing.',
            'brand.enum' => 'Esse tema não está disponível para a landing.',
        ];
    }
}
