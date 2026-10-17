<?php

namespace App\Http\Requests\Notification;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'read' => ['nullable', Rule::in(['all', 'unread', 'read'])],
            'type' => ['nullable', Rule::in(collect(NotificationType::cases())->filter->isCentral()->map->value->all())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'read.in' => 'O filtro de leitura deve ser all, unread ou read.',
            'type.in' => 'O tipo de notificação informado não existe.',
            'per_page.integer' => 'A quantidade por página deve ser um número inteiro.',
            'per_page.min' => 'A quantidade por página deve ser no mínimo 1.',
            'per_page.max' => 'A quantidade por página deve ser no máximo 100.',
        ];
    }
}
