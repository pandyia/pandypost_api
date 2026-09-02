<?php

namespace App\Http\Requests;

use App\Enums\YouTubePrivacyStatus;
use App\Rules\StoragePathRule;
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
        $workspaceUuid = $this->user()?->resolveCurrentAccess()?->workspace?->uuid ?? '';

        return [
            // O vídeo já foi uploaded direto no S3. O client envia apenas o path.
            'media_storage_path' => [
                'required',
                'string',
                new StoragePathRule($workspaceUuid, ['videos', 'images']),
            ],

            // Thumbnail também é um path no S3 (opcional).
            'thumbnail_storage_path' => [
                'nullable',
                'string',
                new StoragePathRule($workspaceUuid, 'thumbnails'),
            ],

            'social_account_uuids' => ['required', 'array', 'min:1'],
            'social_account_uuids.*' => ['required', 'uuid', 'exists:social_accounts,uuid'],
            'title' => [
                'nullable',
                'string',
                'max:100',
            ],
            'caption' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:+5 minutes'],
            'is_short' => ['nullable', 'boolean'],
            
            // Campos dinâmicos do YouTube
            'youtube_privacy_status' => ['nullable', Rule::enum(YouTubePrivacyStatus::class)],
            'youtube_category_id' => ['nullable', 'string', 'max:10'],
            'youtube_tags' => ['nullable', 'array', 'max:50'],
            'youtube_tags.*' => ['string', 'max:50'],
            'youtube_made_for_kids' => ['nullable', 'boolean'],

            // Optional: links this post to a pipeline card, moving it to "scheduled" automatically.
            'pipeline_card_uuid'   => ['nullable', 'uuid', 'exists:content_pipelines,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'media_storage_path.required' => 'O vídeo é obrigatório. Faça o upload primeiro usando o endpoint upload-url.',
            'title.required_if' => 'Para postar no YouTube, você precisa definir um título.',

            'social_account_uuids.required' => 'Selecione ao menos uma conta social.',
            'social_account_uuids.array' => 'O campo de contas sociais deve ser uma lista.',
            'social_account_uuids.min' => 'Selecione ao menos uma conta social.',
            'social_account_uuids.*.required' => 'A conta social selecionada é obrigatória.',
            'social_account_uuids.*.uuid' => 'A conta social informada é inválida.',
            'social_account_uuids.*.exists' => 'A conta social selecionada não foi encontrada.',
            'scheduled_at.after' => 'O agendamento precisa ser de pelo menos 5 minutos no futuro.',
            'youtube_privacy_status.in' => 'A privacidade do YouTube deve ser public, private ou unlisted.',
        ];
    }
}
