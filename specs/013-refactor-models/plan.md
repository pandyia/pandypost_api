# 013 — Plano

## Decisões

| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| D1 | Apagar código morto (RF-1) em vez de comentar ou manter "por compatibilidade". Confirmado por `grep` em `app/ routes/ database/ tests/ config/`. | Deixar `Tenant.php` ("evitar crash do autoload"): nenhuma referência existe. |
| D2 | `ScheduledPost::pending()` passa a ser usado (RF-2) e `scopeDue` é removido. | Apagar `scopePending` também: há 2 call-sites repetindo o mesmo `where`, então o scope tem uso. |
| D3 | Erros de `SocialAccount` entram nos `SocialAccountError`/`SocialAccountException` que já existem, com a mensagem montada como hoje (`tokenRefreshFailed(platform, body)`). | Mensagem fixa sem `body`: muda `error_message` (Q2). `BaseException` tem `withContext`, mas mudaria o texto gravado. |
| D4 | `loginExpiration()` vira consulta pura e o `update(['remember_me'])` sobe para `AuthService::login`. Sem `DB::transaction` (1 query). | Renomear só: mantém a transação inútil e o efeito colateral escondido. |
| D5 | `HasUuid` como trait (RF-10), no padrão de `BelongsToWorkspace`/`HasExpirableToken`. Mantém `$uuid ?: Str::uuid()` (aceita uuid explícito, como hoje). | `HasUuids` do Laravel: gera ordered UUID e troca a chave primária por padrão (`uniqueIds`), muda comportamento. |
| D6 | `BelongsToWorkspace` com early return, sem mudar a semântica de "sem access = sem filtro". | Corrigir o fail-open junto: é segurança (VULN-09), vira apontamento. |
| D7 | `Plan::gatewayPriceIds` com `prices()->withTrashed()->pluck('gateway_price_id')` + histórico por `price_id` via subquery/ids. Ordem e `unique()->values()` mantidos. | Cache em propriedade: estado em model, desnecessário. |

## Arquivos afetados

- Removidos: `Models/Tenant.php`.
- Editados: `Models/{Role,ContentPipeline,ScheduledPost,Notification,Plan,Price,PriceHistory,User,SocialAccount}.php`, `Models/Traits/BelongsToWorkspace.php`, `Observers/RoleObserver.php`; os 11 models do RF-10.
- Chamadores: `Console/Commands/WarmupPostsCommand.php`, `Services/SocialAccountService.php` (RF-2), `Services/AuthService.php` (RF-9), `Exceptions/SocialAccountException.php`, `Enums/Exceptions/SocialAccountError.php` (RF-8).
- Novos: `Models/Traits/HasUuid.php` (Q1) e testes de caracterização em `tests/Feature/`.

## Ordem

1. Caracterização (1.x). 2. Remoções e reuso sem efeito (2.x). 3. Mudanças com troca de chamador (3.x). 4. `HasUuid` por último, por tocar 11 models.

## Riscos

- `BelongsToWorkspace` roda em toda query de 8 models: qualquer erro vaza ou esconde dados entre tenants. Mitigação: teste direto, indireto e sem `currentAccess` antes de mexer, mais `WorkspacePermissionScopeTest` e `AuthorizationVulnerabilitiesTest`.
- `HasUuid`: ordem dos eventos `creating` (o `Audit` e o `AuditableTrait` leem o uuid ao gravar). Rodar a suíte inteira por model migrado, em lotes pequenos.
- `SocialAccountException` tem `code` = HTTP (BaseException) e `\Exception` tinha 0. Os chamadores capturam `Throwable` e usam só `getMessage()`; confirmar que nenhum job usa `getCode()` (`grep` feito: não há).
- `$hidden` em `Plan/Price/PriceHistory`: qualquer `response()->json($model)` direto passaria a omitir `id`. Nenhum Resource é afetado; o `grep` por retorno cru de Plan/Price na task 3.x confirma.
- Testes com `Http::fake` precisam cobrir as URLs reais de refresh (Google, Instagram, TikTok) para provar a equivalência da mensagem de erro.
