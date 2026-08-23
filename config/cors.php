<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| A API é autenticada por Bearer token do Sanctum no modo token: o login
| devolve plainTextToken no corpo (User::createToken()->plainTextToken) e o
| cliente reenvia o valor no header Authorization. Não é o modo SPA/cookie do
| Sanctum — bootstrap/app.php não chama statefulApi(), config/auth.php só
| declara o guard 'web' de sessão e routes/web.php está vazio. Nenhuma rota
| de API emite cookie.
|
| Ou seja: do ponto de vista da autenticação, supports_credentials NÃO é
| necessário. Ele está true aqui por uma restrição do cliente — o frontend
| envia as requisições com withCredentials/credentials:'include' e não está
| sob nosso controle para remover a flag. Quando o browser manda uma
| requisição em modo credenciado e a resposta não traz
| Access-Control-Allow-Credentials: true, ele descarta o corpo inteiro
| ("Response body is not available to scripts"), mesmo com status 200 e
| Access-Control-Allow-Origin correto. Daí a necessidade do header.
|
| POR QUE ISSO É SEGURO AQUI
|
| O risco real de supports_credentials é a combinação com origem curinga: a
| fruitcake/php-cors, com allowed_origins em '*' e credenciais ligadas,
| ecoaria o Origin de QUALQUER site junto com Allow-Credentials: true,
| deixando qualquer página da web fazer requisições autenticadas em nome do
| usuário logado. Essa combinação é impossível nesta configuração: o
| normalizador de allowed_origins abaixo exige host em parse_url(), e '*' não
| tem host — vira null e é filtrado. Um CORS_ALLOWED_ORIGINS='*' resulta em
| lista VAZIA (nenhuma origem aceita), nunca em curinga.
|
| Além disso, hoje não existe cookie para ser carregado numa requisição
| credenciada: o token vive no header, não em cookie, então não há sessão
| ambiente que um site terceiro pudesse explorar via CSRF.
|
| ATENÇÃO AO MANTER
|
| A premissa acima deixa de valer se a API passar a emitir QUALQUER cookie de
| autenticação — statefulApi(), login por sessão, Sanctum SPA, rotas web
| autenticadas. A partir daí, com credenciais ligadas, toda origem desta
| lista ganha o poder de fazer requisições autenticadas com a sessão do
| usuário, e a proteção de CSRF passa a ser obrigatória. Se isso acontecer,
| revise esta lista com o mesmo rigor de uma allowlist de autenticação.
|
| Corolário: nunca adicione a esta lista uma origem que você não controla
| (domínio de terceiro, host de preview público, wildcard de subdomínio via
| allowed_origins_patterns). Com credenciais ligadas, cada entrada é uma
| origem autorizada a agir em nome do usuário.
|
| O caminho de saída é o frontend parar de enviar credenciais — remover
| withCredentials/credentials:'include'. Feito isso, volte supports_credentials
| para false: a autenticação por Bearer token não perde nada.
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
     *
     * A exigência de host também é a barreira que impede curinga: qualquer
     * entrada sem host (como '*') é descartada. Ver o bloco no topo — isso é
     * o que sustenta supports_credentials => true logo abaixo.
     *
     * Lembre que apex e www são origens DIFERENTES para o browser:
     * https://pandy.pro e https://www.pandy.pro precisam ser listadas
     * separadamente.
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

    /*
     * Vazio de propósito. Um padrão é regex, não é normalizado pelo bloco
     * acima e pode casar mais do que se pretende — com credenciais ligadas,
     * um padrão frouxo (ex.: '#^https://.*\.pandy\.pro$#' aplicado a um
     * domínio com subdomínios de terceiros) autorizaria origens hostis a
     * agir em nome do usuário. Prefira listar as origens explicitamente.
     */
    'allowed_origins_patterns' => [],

    /*
     * '*' aqui não vira literal na resposta: a php-cors ecoa de volta o
     * conteúdo de Access-Control-Request-Headers do preflight. Isso importa
     * porque a spec de CORS proíbe o curinga literal em resposta credenciada
     * — o browser recusaria. Como o valor ecoado é a lista concreta pedida
     * pelo cliente, '*' continua válido mesmo com supports_credentials.
     * O mesmo vale para allowed_methods.
     */
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // Era 0: cada request não-simples pagava um OPTIONS extra. Com a lista de
    // origens fixa a resposta de preflight é cacheável pelo browser.
    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    /*
     * true por exigência do cliente, não da autenticação. Leia o bloco do
     * topo antes de mexer: a segurança disso depende de allowed_origins nunca
     * conter curinga (garantido pelo normalizador) e de a API não emitir
     * cookie de autenticação (verdade hoje).
     */
    'supports_credentials' => true,

];
