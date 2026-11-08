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
            'scheduled_at' => ['nullable', 'date', 'after:+'.config('scheduling.min_lead_minutes').' minutes'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after' => 'O agendamento precisa ser de pelo menos '.config('scheduling.min_lead_minutes').' minutos no futuro.',
        ];
    }
}
