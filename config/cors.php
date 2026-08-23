<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| A API é autenticada por Bearer token do Sanctum (o login devolve
| plainTextToken no corpo), não por cookie: bootstrap/app.php não chama
| statefulApi(), routes/web.php está vazio e nenhuma resposta emite cookie.
| Logo supports_credentials é false — com true, e allowed_origins em '*', a
| fruitcake/php-cors ecoa de volta o Origin de QUALQUER site junto com
| Access-Control-Allow-Credentials: true.
|
*/

$origins = env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL'));

return [

    // 'sanctum/csrf-cookie' saiu: sem statefulApi() essa rota não é usada.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     * Um Origin é scheme://host[:porta], sem caminho nem barra final. Como
     * FRONTEND_URL costuma vir com barra ("https://app.pandy.pro/"), o valor
     * é normalizado aqui — senão a comparação falha em silêncio e todo o
     * frontend leva erro de CORS em produção.
     */
    'allowed_origins' => collect(explode(',', (string) $origins))
        ->map(fn ($o) => trim($o))
        ->filter()
        ->map(function ($o) {
            $p = parse_url($o);
            if (empty($p['host'])) {
                return null;
            }
            return ($p['scheme'] ?? 'https') . '://' . $p['host']
                . (isset($p['port']) ? ':' . $p['port'] : '');
        })
        ->filter()
        ->unique()
        ->values()
        ->all(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // Era 0: cada request não-simples pagava um OPTIONS extra. Com a lista de
    // origens fixa a resposta de preflight é cacheável pelo browser.
    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    'supports_credentials' => false,

];
