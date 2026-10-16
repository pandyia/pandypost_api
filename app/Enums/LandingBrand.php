<?php

namespace App\Enums;

/**
 * Temas visuais que a landing pode usar.
 *
 * Cada valor é o `id` de um tema do front (`pandypost_ui/src/themes`).
 * Ao criar um tema novo no front, adicione-o aqui também: é isso que o libera
 * para o super admin escolher. Um valor que o front não conhece cai no tema de fallback dele.
 */
enum LandingBrand: string
{
    case POP = 'pop';
    case VIOLETA = 'violeta';
    case CORAL = 'coral';
    case BAMBU = 'bambu';
    case ROSE = 'rose';

    public static function default(): self
    {
        return self::POP;
    }
}
