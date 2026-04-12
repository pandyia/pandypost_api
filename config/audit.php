<?php

use App\Models\Audit;
use App\Resolvers\UserResolver;
use App\Resolvers\WorkspaceResolver;
use OwenIt\Auditing\Resolvers\IpAddressResolver;
use OwenIt\Auditing\Resolvers\UrlResolver;
use OwenIt\Auditing\Resolvers\UserAgentResolver;

return [
    'implementation' => Audit::class,

    'user' => [
        'morph_prefix' => 'user',
        'guards'       => [
            'sanctum',
            'web',
            'api',
        ],
        'resolver' => UserResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolvers
    |--------------------------------------------------------------------------
    |
    | Cada chave do array vira uma coluna no INSERT da tabela audits.
    | Ex: 'workspace_id' => WorkspaceResolver::class
    |     faz o pacote chamar WorkspaceResolver::resolve($model)
    |     e salvar o retorno na coluna 'workspace_id' automaticamente.
    |
    */
    'resolvers' => [
        'ip_address' => IpAddressResolver::class,
        'user_agent' => UserAgentResolver::class,
        'url'        => UrlResolver::class,
        'workspace_id' => WorkspaceResolver::class,
    ],

    'events' => [
        'created',
        'updated',
        'deleted',
        'restored',
    ],

    'strict' => false,
    'timestamps' => false,
    'threshold' => 0, //aumenta o valor para delimitar a quantidade de logs na auditoria
    'driver' => 'database',

    'drivers' => [
        'database' => [
            'table'      => 'audits',
            'connection' => null,
        ],
    ],

    'console' => false,
];
