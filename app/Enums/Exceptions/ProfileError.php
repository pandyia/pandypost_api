<?php

namespace App\Enums\Exceptions;

use App\Contracts\ErrorEnumInterface;

enum ProfileError: string implements ErrorEnumInterface
{
    case INVALID_AVATAR_FILE = 'profile_invalid_avatar_file';
    case AVATAR_TOO_LARGE = 'profile_avatar_too_large';

    public function message(?string $context = null): string
    {
        return match ($this) {
            self::INVALID_AVATAR_FILE => 'O arquivo enviado não é uma imagem válida. Use JPG, PNG ou WebP.',
            self::AVATAR_TOO_LARGE => "A foto é maior que o limite de {$context} MB. Escolha uma imagem menor.",
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::INVALID_AVATAR_FILE, self::AVATAR_TOO_LARGE => 422,
        };
    }
}
