<?php

namespace App\Rules;

use App\Services\Storage\StorageService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida o `path` de uma foto de perfil já enviada ao S3: precisa ser do próprio usuário
 * (`users/{uuid}/avatars/`) e existir no storage. Tipo e tamanho reais são checados depois,
 * em ProfileService::setAvatar.
 */
class AvatarPathRule implements ValidationRule
{
    public function __construct(private readonly string $userUuid) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('O campo :attribute deve ser um caminho válido no storage.');

            return;
        }

        $storage = app(StorageService::class);

        if (! $storage->pathBelongsToUserAvatars($value, $this->userUuid)) {
            $fail('O caminho informado não é uma foto do seu perfil.');

            return;
        }

        if (! $storage->exists($value)) {
            $fail('O arquivo informado não foi encontrado no storage. Verifique se o upload foi concluído.');
        }
    }
}
