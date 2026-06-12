<?php

namespace App\Rules;

use App\Services\Storage\StorageService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StoragePathRule implements ValidationRule
{
    public function __construct(
        private readonly string $workspaceUuid,
        private readonly string $expectedDirectory,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') {
            $fail('O campo :attribute deve ser um caminho válido no storage.');
            return;
        }

        $storage = app(StorageService::class);

        // 1. Ownership: o path deve pertencer ao workspace do usuário autenticado.
        if (!$storage->pathBelongsToWorkspace($value, $this->workspaceUuid)) {
            $fail('O caminho informado não pertence ao seu workspace.');
            return;
        }

        // 2. Diretório esperado: o path deve estar no diretório correto (ex: videos, thumbnails).
        $expectedPrefix = "workspaces/{$this->workspaceUuid}/{$this->expectedDirectory}/";
        if (!str_starts_with($value, $expectedPrefix)) {
            $fail('O caminho informado não está no diretório esperado.');
            return;
        }

        // 3. Existência: o objeto deve existir no S3 (HEAD request).
        if (!$storage->exists($value)) {
            $fail('O arquivo informado não foi encontrado no storage. Verifique se o upload foi concluído.');
            return;
        }
    }
}
