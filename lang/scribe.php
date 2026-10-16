<?php

// Textos da interface da documentação (Scribe) em pt-BR.

return [
    'labels' => [
        'search' => 'Buscar',
        'base_url' => 'URL base',
    ],

    'auth' => [
        'none' => 'Esta API não exige autenticação.',
        'instruction' => [
            'query' => <<<'TEXT'
                Para autenticar, envie o parâmetro de query **`:parameterName`**.
                TEXT,
            'body' => <<<'TEXT'
                Para autenticar, envie o parâmetro **`:parameterName`** no corpo da requisição.
                TEXT,
            'query_or_body' => <<<'TEXT'
                Para autenticar, envie o parâmetro **`:parameterName`** na query string ou no corpo da requisição.
                TEXT,
            'bearer' => <<<'TEXT'
                Para autenticar, envie o header **`Authorization`** com o valor **`"Bearer :placeholder"`**.
                TEXT,
            'basic' => <<<'TEXT'
                Para autenticar, envie o header **`Authorization`** no formato **`"Basic {credentials}"`**.
                `{credentials}` é usuário e senha separados por dois-pontos (:), em base64.
                TEXT,
            'header' => <<<'TEXT'
                Para autenticar, envie o header **`:parameterName`** com o valor **`":placeholder"`**.
                TEXT,
        ],
        'details' => <<<'TEXT'
            Os endpoints que exigem login têm o selo `requires authentication`. Os demais são públicos.
            TEXT,
    ],

    'headings' => [
        'introduction' => 'Introdução',
        'auth' => 'Autenticação das requisições',
    ],

    'endpoint' => [
        'request' => 'Requisição',
        'headers' => 'Headers',
        'url_parameters' => 'Parâmetros de URL',
        'body_parameters' => 'Corpo da requisição',
        'query_parameters' => 'Parâmetros de query',
        'response' => 'Resposta',
        'response_fields' => 'Campos da resposta',
        'example_request' => 'Exemplo de requisição',
        'example_response' => 'Exemplo de resposta',
        'responses' => [
            'binary' => 'Dados binários',
            'empty' => 'Resposta vazia',
        ],
    ],

    'try_it_out' => [
        'open' => 'Testar ⚡',
        'cancel' => 'Cancelar',
        'send' => 'Enviar',
        'loading' => '⏱ Enviando...',
        'received_response' => 'Resposta recebida',
        'request_failed' => 'A requisição falhou com o erro',
        'error_help' => <<<'TEXT'
            Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
            Os detalhes aparecem no console do navegador.
            TEXT,
    ],

    'links' => [
        'postman' => 'Coleção do Postman',
        'openapi' => 'Especificação OpenAPI',
    ],
];
