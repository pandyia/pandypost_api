# Tasks 019

- [x] 1 VULN-POST-01/BUG-1: `cancel` e `disconnect` com `deleteIfUnused` — teste: `QaConjuntoPublicacaoTest`, `CancelScheduledPostTest`, `FinalAuditSocialPublishTest`
- [x] 2 D1: cancelar/reenviar por permissão no workspace (sem filtro por autor) — teste: `FechamentoPublicacaoTest`
- [x] 3 VULN-TEN-02: `BelongsToWorkspace` falha fechado, `resolveCurrentAccess` com `setRelation`, `ensureValidSocialAccount` com workspace explícito — teste: suíte inteira + `FechamentoPublicacaoTest` (jobs/comandos sem usuário)
- [x] 4 VULN-SCHED-01: `schedules` exige `social_accounts.view` e `posts.view`
- [x] 5 VULN-OAUTH-04/05/06/07: callback genérico, state com platform, `connect` do iniciador, throttle
- [x] 6 VULN-RETRY-03/03b/04 (+ VULN-RETRY-02 skip obsoleto)
- [x] 7 TikTok: `withoutRedirecting`, `markFailed` guardado, D2 (422 no agendamento)
- [x] 8 Mensagem `no_account_linked`; remover `accountAlreadyLinked`
- [x] 9 Remover `->skip` corrigidos; atualizar status em specs/002
- [x] 10 Docs Scribe e `docs/padrao-redes-sociais.md`
- [x] 11 Pint `--dirty` e suíte completa verde 2x

## Divergências
- **D1 (escopo de tenant em jobs/comandos)**: `BelongsToWorkspace` falha fechado só para usuário **autenticado** sem `currentAccess`. Sem usuário autenticado (jobs, comandos, webhooks) o escopo continua aberto, como no código atual; não foi preciso trocar nenhum job/comando por `withoutGlobalScopes()` e o comportamento está coberto por teste (`deleteIfUnused` sem usuário).
- **D2 (teste de caracterização trocado)**: `BelongsToWorkspaceScopeTest` "não filtra nada sem currentAccess (ver VULN-09)" fixava o comportamento aberto; agora afirma o fechado (a asserção foi invertida de propósito, é a própria mudança da spec).
- **D3 (Invite::invitedBy)**: com o escopo fechado, `GET /me/invites` perdia o `invitedBy` para usuário sem acesso; além disso, para quem tem acesso a outro workspace o escopo de `User` já escondia o convidante (bug latente). `Invite::invitedBy()` passou a usar `withoutGlobalScope('workspace')`, como as demais relações do convite.
- **D4 (testes ajustados por exigência nova, asserções mantidas)**: `TikTokAnalyticsTest` (iniciador agora precisa de `social_accounts.connect`), `TikTokPayloadTest` (conta com `video.publish`, senão 422), `FinalAuditSocialPublishTest` regressão do `schedules` (usuário B com `posts.view`), `CancelScheduledPostTest` (`deleteIfUnused` no lugar de `deleteMany`).
- **D5 (callback com plataforma inexistente)**: a plataforma é conferida antes de consumir o state, para manter `?error=Plataforma 'x' não é suportada.`; state de plataforma diferente da rota dá "Estado OAuth inválido.".
- **D6 (schedules)**: a segunda permissão (`posts.view`) é um middleware `permission:posts.view` na rota (mecanismo existente), a primeira vem de `$permissionMethods`.
- **D7 (validação do TikTok no agendamento)**: contas são conferidas todas antes de criar qualquer post (nenhum job despachado em caso de erro); erro de conta vem antes de erro de campos do payload.
- **D8 (logs)**: `PublishPostJob::failed` e `InstagramService::processContainer` logam classe e código de domínio na própria mensagem (sem contexto estruturado).
- **D9**: `ScheduledPostService::cancel` não recebe mais o `User` (não há filtro por autor).

## Pendências (registradas)
- D3: VULN-SA-01 (tokens em texto puro) e VULN-UP-01 (presigned PUT sem limite) deferidos; testes em `->skip`.
- D4: histórico de posts some ao desconectar a conta (decisão futura).
- D5: `consumeQuota`/assinatura no-op (FASE 2).
