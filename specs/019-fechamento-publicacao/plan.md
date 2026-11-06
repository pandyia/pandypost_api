# Plano 019

## Decisões técnicas
1. **Mídia compartilhada**: `ScheduledPostService::cancel` e `SocialAccountService::disconnect` usam `StorageService::deleteIfUnused($paths, $postId)` (já existente). No `disconnect`, posts pendentes são cancelados um a um (status primeiro, mídia depois), para que a checagem de "em uso" ignore os recém-cancelados. Descartado: manter `deleteMany` com contagem manual.
2. **D1**: remover `where('user_id', ...)` do `cancel`. O escopo de tenant (via conta) já isola workspaces. `retry` já era por workspace; ganha teste que o fixa.
3. **Falha fechada**: em `BelongsToWorkspace`, com usuário autenticado e sem `currentAccess` o scope aplica `whereRaw('1 = 0')`. Sem usuário autenticado segue aberto (jobs/comandos/webhooks; os que precisam de escopo explícito já usam `withoutGlobalScopes()`). `resolveCurrentAccess` usa `setRelation('currentAccess', $access)`. Descartado: falhar fechado também sem usuário (quebra jobs, `StorageService::deleteIfUnused` em fila, snapshot, purge, warmup, webhooks).
4. **schedules**: `$permissionMethods` mapeia `schedules` em `view`; `posts.view` validada no método com `CheckPermission`-equivalente (`hasPermission` + `PermissionDeniedException`/resposta 403 padrão). O mecanismo só aceita uma permissão por ação.
5. **OAuth**: `OAuthStateService::issue($user, $workspace, $platform)` e `consume($state, $platform)`. `AbstractOAuthProvider::stateFor` passa a plataforma (`static::PLATFORM`/chave do provider). Callback: `catch (BaseException)` mostra mensagem; `Throwable` genérica + `Log::warning` só com `get_class`. Permissão: `hasPermissionInWorkspace('social_accounts.connect', $workspace->id)` (super admin passa). Throttle: `throttle:20,1` na rota.
6. **Permalink**: validação no `InstagramApi::permalink`; retorno vira `?string` tratado pelo chamador existente.
7. **TikTok**: `Http::withoutRedirecting()->put`; `markFailed` guarda de status; `ScheduledPostService` valida `canPublish()` do TikTok antes de criar posts (422 `ValidationException`, tudo ou nada dentro da transação).
8. **Mensagem**: `NO_ACCOUNT_LINKED` sem placeholder.

## Arquivos
`ScheduledPostService`, `SocialAccountService`, `BelongsToWorkspace`, `User`, `SocialAccountController`, `OAuthStateService`, `AbstractOAuthProvider` (+ providers), `routes/api.php`, `InstagramApi`, `InstagramService`, `PublishPostJob`, `TikTokApi`/`TikTokService`, `CheckTikTokPostStatusJob`, `ScheduledPostError`, `SocialAccountException`/`SocialAccountError`, controllers (Scribe), `docs/padrao-redes-sociais.md`, specs 002 e 019, testes.

## Efeitos colaterais
- Usuário sem `access_id` e sem workspace pessoal passa a ver listas vazias (antes via tudo).
- State emitido antes do deploy (sem platform) fica inválido (vida de 10 min).
- Cancelar por não-autor muda de 404 para 200.

## Testes
Ajuste de `CancelScheduledPostTest`; novos em `tests/Feature/ScheduledPosts/FechamentoPublicacaoTest.php` (D1, D2, disconnect, mensagens) e remoção dos skips em `FinalAuditSocialPublishTest`; testes de jobs/comandos sem usuário (scope aberto consciente).

## Riscos
Falha fechada (maior risco): mitigado rodando a suíte inteira e cobrindo contextos sem usuário.
