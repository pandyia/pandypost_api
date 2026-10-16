<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\configureStrategy;
use function Knuckles\Scribe\Config\removeStrategies;

// O Scribe é dependência de dev. Em produção (composer --no-dev) as classes abaixo
// não existem e o config:cache quebraria a aplicação inteira.
if (! class_exists(Defaults::class)) {
    return [];
}

// Only the most common configs are shown. See the https://scribe.knuckles.wtf/laravel/reference/config for all.

return [
    // The HTML <title> for the generated documentation.
    'title' => 'Pandypost API',

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => 'Referência da API do Pandypost para o frontend.',

    // Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
    'intro_text' => <<<'INTRO'
        API do Pandypost: agendamento e publicação de vídeos no YouTube, TikTok e Instagram. Esta página é o contrato entre a API e o frontend.

        ## Como a API funciona

        **Base URL:** todas as rotas ficam sob `/api`. Envie e espere sempre JSON.

        **Autenticação:** `POST /api/login` devolve um `token`. Mande-o em todas as rotas protegidas:
        `Authorization: Bearer {token}`. Rotas marcadas como públicas não precisam dele.

        **Email verificado:** quase toda rota exige email confirmado. Sem isso a resposta é
        `403 {"error": "email_not_verified"}` → leve o usuário para a tela de confirmação. Só `/me`, `/logout` e o
        reenvio do código funcionam antes da confirmação.

        **Workspace corrente:** o sistema é multi-tenant. Cada usuário tem um workspace corrente (o pessoal, por padrão)
        e **todas** as leituras e escritas são feitas nele. Para trocar, use `POST /api/workspaces/{uuid}/switch`.
        Recurso de outro workspace responde `404`.

        **Permissões:** cada endpoint informa a permissão exigida (ex.: `users.view`). A lista do usuário no workspace
        corrente vem em `GET /api/me` → `permissions`; use-a para esconder botões. Sem permissão:
        `403 {"message": "...", "required_permission": "users.view"}`. Super admin passa em tudo.

        **Identificadores:** as rotas usam `uuid`. Exceções estão indicadas no próprio endpoint.

        ## Listagens

        Listagens aceitam `per_page` e `page`. **Sem `per_page`, vem tudo numa página só.** Filtros de texto são
        parciais e sem diferenciar maiúsculas (`?name=ana`). Formato:

        ```json
        {
          "data": [ ... ],
          "links": { "first": "...", "last": "...", "prev": null, "next": null },
          "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 3 }
        }
        ```

        <aside class="notice">Algumas listagens (marcadas no endpoint) devolvem o paginador "cru" do Laravel:
        os itens em <code>data</code> e os campos de paginação (<code>current_page</code>, <code>total</code>, ...) na raiz.</aside>

        ## Erros

        | Status | Quando | Corpo |
        |---|---|---|
        | 401 | Token ausente ou inválido | `{"message": "Não autenticado"}` |
        | 403 | Sem permissão / email não verificado | `{"message": "..."}` |
        | 404 | Não existe ou é de outro workspace | `{"message": "..."}` |
        | 422 | Validação | `{"message": "...", "errors": {"campo": ["..."]}}` |
        | 429 | Muitas tentativas | `{"error": "TOO_MANY_ATTEMPTS", "message": "..."}` |
        | 4xx/5xx | Regra de negócio | `{"error": "codigo_do_erro", "message": "..."}` |

        Erros de regra de negócio sempre trazem `error` (código estável, use-o no `if`) e `message` (texto em pt-BR,
        pronto para exibir). Os códigos possíveis aparecem em cada endpoint.

        ## Datas e valores

        - Datas em ISO 8601 (`2026-10-15T14:00:00-03:00`), salvo onde o exemplo mostrar outro formato.
        - Valores monetários em **centavos** (`4990` = R$ 49,90).

        ## Tempo real (Reverb)

        Eventos chegam por WebSocket (Laravel Echo + Reverb). Autorize canais privados em `POST /api/broadcasting/auth`
        com o mesmo `Authorization: Bearer {token}`.

        | Canal | Evento | Quando |
        |---|---|---|
        | `private-workspaces.{uuid}` | `.RoleUpdated` | Um perfil do workspace foi criado, editado ou removido. Payload: `{"action": "created\|updated\|deleted\|restored", "role": {...}}`. Recarregue `/me` para atualizar as permissões. |

        <aside class="warning">Endpoints marcados com ⚠️ têm defeito conhecido registrado em
        <code>specs/002-security-hardening</code>. Não dependa deles até a correção.</aside>
        INTRO,

    // The base URL displayed in the docs.
    // If you're using `laravel` type, you can set this to a dynamic string, like '{{ config("app.tenant_url") }}' to get a dynamic base URL.
    'base_url' => config('app.url'),

    // Routes to include in the docs
    'routes' => [
        [
            'match' => [
                // Match only routes whose paths match this pattern (use * as a wildcard to match any characters). Example: 'users/*'.
                'prefixes' => ['api/*'],

                // Match only routes whose domains match this pattern (use * as a wildcard to match any characters). Example: 'api.*'.
                'domains' => ['*'],
            ],

            // Include these routes even if they did not match the rules above.
            'include' => [
                // 'users.index', 'POST /new', '/auth/*'
            ],

            // Exclude these routes even if they matched the rules above.
            'exclude' => [
                // Webhook do Stripe: chamado só pelo Stripe, não pelo frontend.
                'POST /api/stripe/webhook',
                // Autorização de canais do Reverb: descrita na introdução.
                'api/broadcasting/auth',
            ],
        ],
    ],

    // The type of documentation output to generate.
    // - "static" will generate a static HTMl page in the /public/docs folder,
    // - "laravel" will generate the documentation as a Blade view, so you can add routing and authentication.
    // - "external_static" and "external_laravel" do the same as above, but pass the OpenAPI spec as a URL to an external UI template
    'type' => 'laravel',

    // See https://scribe.knuckles.wtf/laravel/reference/config#theme for supported options
    'theme' => 'default',

    'static' => [
        // HTML documentation, assets and Postman collection will be generated to this folder.
        // Source Markdown will still be in resources/docs.
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        // Whether to automatically create a docs route for you to view your generated docs. You can still set up routing manually.
        'add_routes' => true,

        // URL path to use for the docs endpoint (if `add_routes` is true).
        // By default, `/docs` opens the HTML page, `/docs.postman` opens the Postman collection, and `/docs.openapi` the OpenAPI spec.
        'docs_url' => '/docs',

        // Directory within `public` in which to store CSS and JS assets.
        // By default, assets are stored in `public/vendor/scribe`.
        // If set, assets will be stored in `public/{{assets_directory}}`
        'assets_directory' => null,

        // Middleware to attach to the docs endpoint (if `add_routes` is true).
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        // Add a Try It Out button to your endpoints so consumers can test endpoints right from their browser.
        // Don't forget to enable CORS headers for your endpoints.
        'enabled' => true,

        // The base URL to use in the API tester. Leave as null to be the same as the displayed URL (`scribe.base_url`).
        'base_url' => null,

        // [Laravel Sanctum] Fetch a CSRF token before each request, and add it as an X-XSRF-TOKEN header.
        'use_csrf' => false,

        // The URL to fetch the CSRF token from (if `use_csrf` is true).
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    // How is your API authenticated? This information will be used in the displayed docs, generated examples and response calls.
    'auth' => [
        // Set this to true if ANY endpoints in your API use authentication.
        'enabled' => true,

        // Set this to true if your API should be authenticated by default. If so, you must also set `enabled` (above) to true.
        // You can then use @unauthenticated or @authenticated on individual endpoints to change their status from the default.
        'default' => true,

        // Where is the auth value meant to be sent in a request?
        'in' => AuthIn::BEARER->value,

        // The name of the auth parameter (e.g. token, key, apiKey) or header (e.g. Authorization, Api-Key).
        'name' => 'Authorization',

        // The value of the parameter to be used by Scribe to authenticate response calls.
        // This will NOT be included in the generated documentation. If empty, Scribe will use a random value.
        'use_value' => env('SCRIBE_AUTH_KEY'),

        // Placeholder your users will see for the auth parameter in the example requests.
        // Set this to null if you want Scribe to use a random value as placeholder instead.
        'placeholder' => '{TOKEN}',

        // Any extra authentication-related info for your users. Markdown and HTML are supported.
        'extra_info' => 'Obtenha o token em <code>POST /api/login</code> (campo <code>token</code>). Ele vale até o logout ou a expiração definida pelo <code>remember_me</code>.',
    ],

    // Example requests for each endpoint will be shown in each of these languages.
    // Supported options are: bash, javascript, php, python
    // To add a language of your own, see https://scribe.knuckles.wtf/laravel/advanced/example-requests
    // Note: does not work for `external` docs types
    'example_languages' => [
        'javascript',
        'bash',
    ],

    // Generate a Postman collection (v2.1.0) in addition to HTML docs.
    // For 'static' docs, the collection will be generated to public/docs/collection.json.
    // For 'laravel' docs, it will be generated to storage/app/scribe/collection.json.
    // Setting `laravel.add_routes` to true (above) will also add a route for the collection.
    'postman' => [
        'enabled' => true,

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    // Generate an OpenAPI spec in addition to docs webpage.
    // For 'static' docs, the collection will be generated to public/docs/openapi.yaml.
    // For 'laravel' docs, it will be generated to storage/app/scribe/openapi.yaml.
    // Setting `laravel.add_routes` to true (above) will also add a route for the spec.
    'openapi' => [
        'enabled' => true,

        // The OpenAPI spec version to generate. Supported versions: '3.0.3', '3.1.0'.
        // OpenAPI 3.1 is more compatible with JSON Schema and is becoming the dominant version.
        // See https://spec.openapis.org/oas/v3.1.0 for details on 3.1 changes.
        'version' => '3.0.3',

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],

        // Additional generators to use when generating the OpenAPI spec.
        // Should extend `Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator`.
        'generators' => [],
    ],

    'groups' => [
        // Endpoints which don't have a @group will be placed in this default group.
        'default' => 'Endpoints',

        // By default, Scribe will sort groups alphabetically, and endpoints in the order their routes are defined.
        // You can override this by listing the groups, subgroups and endpoints here in the order you want them.
        // See https://scribe.knuckles.wtf/blog/laravel-v4#easier-sorting and https://scribe.knuckles.wtf/laravel/reference/config#order for details
        // Note: does not work for `external` docs types
        'order' => [
            'Autenticação',
            'Workspaces',
            'Usuários',
            'Perfis e permissões',
            'Acessos',
            'Convites',
            'Contas sociais',
            'Agendamentos',
            'Pipeline de conteúdo',
            'Analytics',
            'Auditoria',
            'Planos (público)',
            'Assinatura e pagamentos',
            'Admin: planos e preços',
        ],
    ],

    // Custom logo path. This will be used as the value of the src attribute for the <img> tag,
    // so make sure it points to an accessible URL or path. Set to false to not use a logo.
    // For example, if your logo is in public/img:
    // - 'logo' => '../img/logo.png' // for `static` type (output folder is public/docs)
    // - 'logo' => 'img/logo.png' // for `laravel` type
    'logo' => false,

    // Customize the "Last updated" value displayed in the docs by specifying tokens and formats.
    // Examples:
    // - {date:F j Y} => March 28, 2022
    // - {git:short} => Short hash of the last Git commit
    // Available tokens are `{date:<format>}` and `{git:<format>}`.
    // The format you pass to `date` will be passed to PHP's `date()` function.
    // The format you pass to `git` can be either "short" or "long".
    // Note: does not work for `external` docs types
    'last_updated' => 'Atualizado em {date:d/m/Y} ({git:short})',

    'examples' => [
        // Set this to any number to generate the same example values for parameters on each run,
        'faker_seed' => 1234,

        // With API resources and transformers, Scribe tries to generate example models to use in your API responses.
        // By default, Scribe will try the model's factory, and if that fails, try fetching the first from the database.
        // You can reorder or remove strategies here.
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    // The strategies Scribe will use to extract information about your routes at each stage.
    // Use configureStrategy() to specify settings for a strategy in the list.
    // Use removeStrategies() to remove an included strategy.
    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        // As regras do AnalyticsDashboardRequest dependem da conta da rota (capacidades
        // por plataforma), então os parâmetros desse endpoint ficam no @queryParam.
        'queryParameters' => configureStrategy(
            Defaults::QUERY_PARAMETERS_STRATEGIES,
            Strategies\QueryParameters\GetFromFormRequest::wrapWithSettings(except: ['GET api/analytics/*']),
        ),
        'bodyParameters' => configureStrategy(
            Defaults::BODY_PARAMETERS_STRATEGIES,
            Strategies\BodyParameters\GetFromFormRequest::wrapWithSettings(except: ['GET api/analytics/*']),
        ),
        // Sem response calls: os exemplos vêm das anotações @response nos controllers,
        // então gerar a doc nunca toca o banco nem APIs externas.
        'responses' => removeStrategies(
            Defaults::RESPONSES_STRATEGIES,
            [Strategies\Responses\ResponseCalls::class],
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    // For response calls, API resource responses and transformer responses,
    // Scribe will try to start database transactions, so no changes are persisted to your database.
    // Tell Scribe which connections should be transacted here. If you only use one db connection, you can leave this as is.
    'database_connections_to_transact' => [],

    'fractal' => [
        // If you are using a custom serializer with league/fractal, you can specify it here.
        'serializer' => null,
    ],
];
