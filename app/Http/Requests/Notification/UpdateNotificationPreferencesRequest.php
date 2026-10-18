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
            'in_app' => ['sometimes', 'array'],
            'in_app.invite' => ['sometimes', 'boolean'],
            'in_app.posts' => ['sometimes', 'boolean'],
            'in_app.social_accounts' => ['sometimes', 'boolean'],
            'in_app.post_published' => ['sometimes', 'boolean'],
            'in_app.billing' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'invite.boolean' => 'A preferência de convites deve ser verdadeira ou falsa.',
            'posts.boolean' => 'A preferência de posts deve ser verdadeira ou falsa.',
            'social_accounts.boolean' => 'A preferência de contas sociais deve ser verdadeira ou falsa.',
            'post_published.boolean' => 'A preferência de e-mail de post publicado deve ser verdadeira ou falsa.',
            'in_app.post_published.boolean' => 'O aviso no app de post publicado deve ser verdadeiro ou falso.',
            'billing.prohibited' => 'Os e-mails de cobrança não podem ser desativados.',
            'in_app.invite.boolean' => 'O aviso no app de convites deve ser verdadeiro ou falso.',
            'in_app.posts.boolean' => 'O aviso no app de posts deve ser verdadeiro ou falso.',
            'in_app.social_accounts.boolean' => 'O aviso no app de contas sociais deve ser verdadeiro ou falso.',
            'in_app.billing.prohibited' => 'Os avisos de cobrança não podem ser desativados.',
        ];
    }
}
