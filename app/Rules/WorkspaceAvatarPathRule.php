<?php

namespace App\Rules;

use App\Services\Storage\StorageService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida o `path` da foto de um workspace já enviada ao S3: precisa ser desse workspace
 * (`workspaces/{uuid}/avatar/`) e existir no storage. Tipo e tamanho reais são checados depois,
 * em WorkspaceService::setAvatar.
 */
class WorkspaceAvatarPathRule implements ValidationRule
{
    public function __construct(private readonly string $workspaceUuid) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('O campo :attribute deve ser um caminho válido no storage.');

            return;
        }

        $storage = app(StorageService::class);

        if (! $storage->pathBelongsToWorkspaceAvatar($value, $this->workspaceUuid)) {
            $fail('O caminho informado não é uma foto deste workspace.');

            return;
        }

        if (! $storage->exists($value)) {
            $fail('O arquivo informado não foi encontrado no storage. Verifique se o upload foi concluído.');
        }
    }
}
