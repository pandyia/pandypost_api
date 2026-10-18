<?php

/*
|--------------------------------------------------------------------------
| Testes de segurança — origens do WebSocket (Reverb)
|--------------------------------------------------------------------------
| O Reverb compara só o host do header Origin com allowed_origins, via
| Str::is. Estes testes avaliam config/reverb.php com env controlado e
| garantem que a lista nunca libera qualquer origem.
*/

/**
 * Avalia config/reverb.php com as variáveis informadas (null = ausente) e
 * devolve o allowed_origins do app. Restaura o ambiente ao final.
 */
function reverbAllowedOrigins(array $vars): array
{
    $previous = [];

    foreach ($vars as $key => $value) {
        $previous[$key] = getenv($key);

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }

    try {
        $config = require base_path('config/reverb.php');
    } finally {
        foreach ($previous as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
    }

    return $config['apps']['apps'][0]['allowed_origins'];
}

describe('Reverb — origens permitidas', function () {
    it('usa o host do FRONTEND_URL em produção', function () {
        expect(reverbAllowedOrigins([
            'REVERB_ALLOWED_ORIGINS' => null,
            'FRONTEND_URL' => 'https://pandy.pro/',
        ]))->toBe(['pandy.pro']);
    });

    it('usa o host do FRONTEND_URL em dev, ignorando a porta', function () {
        expect(reverbAllowedOrigins([
            'REVERB_ALLOWED_ORIGINS' => null,
            'FRONTEND_URL' => 'http://localhost:5173',
        ]))->toBe(['localhost']);
    });

    it('prioriza REVERB_ALLOWED_ORIGINS e aceita URL ou host puro', function () {
        expect(reverbAllowedOrigins([
            'REVERB_ALLOWED_ORIGINS' => 'https://pandy.pro, www.pandy.pro ,PANDY.PRO',
            'FRONTEND_URL' => 'http://localhost:5173',
        ]))->toBe(['pandy.pro', 'www.pandy.pro']);
    });

    it('descarta curingas em vez de liberar qualquer origem', function () {
        expect(reverbAllowedOrigins([
            'REVERB_ALLOWED_ORIGINS' => '*, *.pandy.pro, https://pandy.pro',
            'FRONTEND_URL' => null,
        ]))->toBe(['pandy.pro']);
    });

    it('fica vazia (recusa tudo) quando nada está configurado', function () {
        expect(reverbAllowedOrigins([
            'REVERB_ALLOWED_ORIGINS' => null,
            'FRONTEND_URL' => null,
        ]))->toBe([]);
    });
});
