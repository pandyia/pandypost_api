<?php

namespace App\Http\Requests;

use App\Enums\Platform;
use App\Enums\YouTubePrivacyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime',
                'max:102400'
            ],
            'platform' => ['required', Rule::enum(Platform::class)],
            'social_account_uuid' => ['required', 'uuid', 'exists:social_accounts,uuid'],
            'title' => [
                'required_if:platform,youtube',
                'nullable',
                'string',
                'max:100'
            ],
            'caption' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:+5 minutes'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'is_short' => ['nullable', 'boolean'],
            
            // Campos dinâmicos do YouTube
            'youtube_privacy_status' => ['nullable', Rule::enum(YouTubePrivacyStatus::class)],
            'youtube_category_id' => ['nullable', 'string', 'max:10'],
            'youtube_tags' => ['nullable', 'array', 'max:50'],
            'youtube_tags.*' => ['string', 'max:50'],
            'youtube_made_for_kids' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'video.max' => 'O vídeo é muito grande! O limite é de 100MB.',
            'video.mimetypes' => 'Formato inválido. Aceitamos apenas MP4 e MOV.',
            'title.required_if' => 'Para postar no YouTube, você precisa definir um título.',
            'platform.in' => 'A plataforma selecionada não é suportada.',
            'social_account_uuid.required' => 'A conta social selecionada é obrigatória.',
            'social_account_uuid.uuid' => 'A conta social informada é inválida.',
            'social_account_uuid.exists' => 'A conta social selecionada não foi encontrada.',
            'scheduled_at.after' => 'O agendamento precisa ser de pelo menos 5 minutos no futuro.',
            'thumbnail.image' => 'A capa deve ser uma imagem válida.',
            'thumbnail.max' => 'A capa não pode ultrapassar 2MB (Limite Oficial do YouTube).',
            'youtube_privacy_status.in' => 'A privacidade do YouTube deve ser public, private ou unlisted.',
        ];
    }
}
