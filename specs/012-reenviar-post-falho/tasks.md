# Tasks 012 — Reenviar post que falhou

- [x] Migration `failed_at` e `media_purged_at` (com backfill) — teste: roda nos testes (SQLite) e no Postgres de dev
- [x] Model: constante, `canRetry()`, `retryDeadline()`, observers de falha — teste: `falha de publicação`
- [x] Falha não apaga a mídia (`PublishPostJob`, `CheckTikTokPostStatusJob`) — teste: `não apaga a mídia, marca failed_at`
- [x] `deleteIfUnused` protege mídia de post falho no prazo — teste: `deleteIfUnused com posts falhos`
- [x] Erros de domínio `retry_not_allowed` e `media_expired` — teste: 409 e 422 do reenvio
- [x] `ScheduledPostService::retry` + `RetryScheduledPostRequest` — teste: reenvio imediato, com `scheduled_at`, validação
- [x] Rota, `ScheduledPostController::retry` (`posts.create`) e docblock Scribe — teste: 403, 404 de outro workspace, outro membro
- [x] Resource: `can_retry`, `retry_until`, `media_url` nulo após limpeza — teste: `can_retry na listagem`
- [x] Comando `posts:purge-failed-media` + agendamento diário — teste: `PurgeFailedPostsMediaTest`
- [x] Notificação de falha com prazo e dedupe por falha — teste: `notifica outra vez`, `falha fora do PublishPostJob`
- [x] Frontend (spec 017 do `pandypost_ui`) — verificação: `vue-tsc` sem erros novos e `vite build` ok

## Divergências

- Aprovação de plan/tasks dispensada pelo usuário ("sim, implementar tudo já"); estes documentos foram escritos depois da implementação.
- `qa`, `security` (área crítica) e `docs` do fluxo de agentes **não rodaram**: a suíte e os testes novos foram escritos por quem implementou. Pendente.
- Não verificado em navegador (375px, light/dark) nem pelos agentes `ui-ux` e `responsive` do frontend.
- Alteradas pelo Pint trechos antigos de `ScheduledPostService`, `ScheduledPostResource` e `CheckTikTokPostStatusJob` (formatação).

## Apontamentos (fora do escopo)

- 3 testes de `TikTokIntegrationTest` já falhavam antes (spec 011 pendente).
- `CheckInstagramContainerJob` só consulta `status_code`; o motivo real do `ERROR` se perde.
- `cancel()` só aceita `pending`: um post `failed` não pode ser excluído pelo usuário.
- Miniatura (`thumbnail_path`) nunca conta como "em uso" no `deleteIfUnused` (a consulta olha só `media_path`).
