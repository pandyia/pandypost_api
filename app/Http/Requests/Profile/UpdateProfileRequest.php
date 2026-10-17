<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Telefone só com dígitos: aceita "(11) 91234-5678" e vazio vira null. */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            $digits = preg_replace('/\D/', '', $phone);
            $this->merge(['phone' => $digits === '' ? null : $digits]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'phone' => ['nullable', 'string', 'digits_between:10,11'],
            'bio' => ['nullable', 'string', 'max:160'],
            'timezone' => ['required', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.min' => 'O nome precisa ter pelo menos 3 caracteres.',
            'name.max' => 'O nome não pode ter mais de 80 caracteres.',
            'phone.digits_between' => 'Informe o telefone com DDD (10 ou 11 dígitos).',
            'bio.max' => 'A bio não pode ter mais de 160 caracteres.',
            'timezone.required' => 'Escolha o seu fuso horário.',
            'timezone.timezone' => 'Esse fuso horário não é válido.',
        ];
    }
}
