<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invite' => ['sometimes', 'boolean'],
            'posts' => ['sometimes', 'boolean'],
            'social_accounts' => ['sometimes', 'boolean'],
            'post_published' => ['sometimes', 'boolean'],
            'billing' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'invite.boolean' => 'A preferência de convites deve ser verdadeira ou falsa.',
            'posts.boolean' => 'A preferência de posts deve ser verdadeira ou falsa.',
            'social_accounts.boolean' => 'A preferência de contas sociais deve ser verdadeira ou falsa.',
            'post_published.boolean' => 'A preferência de post publicado deve ser verdadeira ou falsa.',
            'billing.prohibited' => 'Os e-mails de cobrança não podem ser desativados.',
        ];
    }
}
