<?php

namespace App\Services\Payloads\Builders;

use App\Enums\TikTokPrivacyLevel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TikTokPayloadBuilder extends AbstractPlatformPayloadBuilder
{
    private const BOOLEAN_FIELDS = ['disable_comment', 'disable_duet', 'disable_stitch', 'brand_content_toggle', 'brand_organic_toggle'];

    // Limite do título/legenda do TikTok.
    public const MAX_TITLE_LENGTH = 2200;

    protected function extractPayload(array &$attributes): array
    {
        $payload = parent::extractPayload($attributes);

        $this->validateMediaExtension($attributes, ['mp4', 'mov', 'webm'], 'O TikTok aceita apenas vídeo (MP4, MOV ou WebM).');

        $input = ['privacy_level' => $this->pull($attributes, 'privacy_level')];
        foreach (self::BOOLEAN_FIELDS as $field) {
            $input[$field] = $this->pull($attributes, $field);
        }
        $input['caption'] = $attributes['caption'] ?? null;

        $validator = Validator::make($input, [
            'privacy_level' => ['required', Rule::enum(TikTokPrivacyLevel::class)],
            ...array_fill_keys(self::BOOLEAN_FIELDS, ['nullable', 'boolean']),
            'caption' => ['nullable', 'string', 'max:'.self::MAX_TITLE_LENGTH],
        ], [
            'privacy_level.required' => 'Escolha a privacidade do vídeo no TikTok.',
            'privacy_level.enum' => 'A privacidade escolhida não existe no TikTok.',
            'caption.max' => 'A legenda do TikTok pode ter no máximo '.self::MAX_TITLE_LENGTH.' caracteres.',
        ]);

        $validator->after(function ($validator) use ($input) {
            if ($this->normalizeBoolean($input['brand_content_toggle']) && $input['privacy_level'] === TikTokPrivacyLevel::SELF_ONLY->value) {
                $validator->errors()->add('brand_content_toggle', 'Conteúdo de marca não pode ser publicado com privacidade "somente eu".');
            }
        });

        $validator->validate();

        $payload['privacy_level'] = $input['privacy_level'];
        foreach (self::BOOLEAN_FIELDS as $field) {
            $value = $this->normalizeBoolean($input[$field]);
            if ($value !== null) {
                $payload[$field] = $value;
            }
        }

        return $payload;
    }
}
