<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryScheduledPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Sem data, o reenvio é imediato.
            'scheduled_at' => ['nullable', 'date', 'after:+5 minutes'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after' => 'O agendamento precisa ser de pelo menos 5 minutos no futuro.',
        ];
    }
}
