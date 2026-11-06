# 017 — Conta social pertence ao workspace + OAuth `state` seguro

## Contexto
Hoje a conta social guarda `user_id` e o agendamento só a aceita se `user_id` for do próprio usuário (`ScheduledPostService::ensureValidSocialAccount`; `PublishPostJob` também confere). No callback do OAuth a conta fica com o `user_id` do **primeiro membro** do workspace, não de quem conectou, e o `state` é só `encrypt(workspace_uuid)`: sem nonce, sem expiração, sem usuário, reutilizável (OAUTH-01 / VULN-TT-03 da spec 002). Resultado: em workspace com mais de um membro, o agendamento pode falhar por um dono "errado", e reconectar pode trocar o dono e quebrar posts já criados.

## Decisão de negócio (Gabriel, 2026-11-05)
**A conta social pertence ao workspace.** Quem tem acesso ao workspace e a permissão necessária pode agendar nessa conta, independente de quem a conectou ou reconectou.

## Spec reversa (áreas críticas: OAuth social, permissões/workspace, esteira de publicação)
- `SocialAccount` usa `BelongsToWorkspace` (global scope pelo workspace corrente) e tem `user_id` em `$fillable`.
- `ScheduledPostService::ensureValidSocialAccount()` filtra por `user_id` do usuário; `PublishPostJob::handle()` exige `account->user_id === post->user_id`.
- `getRedirectUrl()` (Google, Instagram, TikTok) usa `encrypt($workspace->uuid)` como `state`; `SocialAccountController::callback` faz `decrypt($state)` e `syncAccount(Workspace)`, que grava `user_id` do primeiro `access` do workspace.
- `ScheduledPost.user_id` é quem agendou (continua).

## Escopo
Backend. Duas mudanças: (A) autorização por workspace; (B) `state` do OAuth seguro.

## Requisitos
- RF-1: o agendamento aceita qualquer `SocialAccount` do **workspace corrente** (global scope), sem filtro por `user_id`. Conta de outro workspace → mesmo erro de hoje (`noAccountLinked`), nada muda no banco.
- RF-2: `PublishPostJob` valida que o autor do post tem acesso ao **workspace da conta** (o post não tem `workspace_id`), não ao `user_id` da conta.
- RF-3: `SocialAccount.user_id` passa a significar "quem conectou/reconectou por último" (informativo/auditoria); não participa de autorização. Reconectar uma conta existente (`updateOrCreate`) **não** quebra agendamentos existentes.
- RF-4: `state` do OAuth (todas as redes) leva `workspace_uuid`, `user_id` (quem iniciou), `nonce` e expiração curta (10 min), e é de **uso único** (nonce consumido no callback, via cache). Replay, expirado, adulterado ou sem nonce → `error=` no redirect, nenhuma conta criada.
- RF-5: o callback grava `user_id` = o usuário que iniciou o fluxo (do `state`), e não o primeiro membro.
- RF-6: listagem/uso das contas (`index`, `schedules`, `creator-info`, `analytics`) continua escopada ao workspace e às permissões de hoje (`social_accounts.*`); nenhuma delas filtra por `user_id`.
- RF-7: o `state` e o nonce nunca aparecem em log nem em resposta de erro.

## Contrato de API
Sem rota nova. `GET /social-accounts/{platform}/auth` devolve a mesma `url` (com `state` novo). `GET /social-accounts/{platform}/callback` mantém o redirect para `{FRONTEND_URL}/social-accounts/callback?success=1|error=...`. Agendar com conta de outro membro do mesmo workspace passa a ser 2xx (antes: erro).

## Critérios de aceite (Pest)
- Membro B agenda em conta conectada pelo membro A do mesmo workspace (com `posts.*`); sem a permissão → 403; conta de outro workspace → erro e banco intacto.
- `PublishPostJob` publica quando conta e post são do mesmo workspace, mesmo com `user_id` diferentes; falha de domínio quando de workspaces diferentes.
- Reconexão por outro membro mantém posts pendentes publicáveis.
- `state`: válido (1ª vez) cria/atualiza conta com `user_id` do iniciador; replay, expirado, adulterado, sem nonce, de outro workspace → `error=` e nada criado. Vale para Google, Instagram e TikTok.
- Os testes `->skip('VULN-TT-03 ...')` e de OAUTH-01 perdem o `skip`.

## Fora de escopo
- Mudar o modelo de permissões; permissão por conta (ex.: restringir contas por membro) — se for desejado, spec futura.
- OAUTH-02 (stubs) e demais itens da spec 002.
- Front (nenhuma mudança de contrato).

## Questões em aberto
Nenhuma.

## Decisões da fase TDD
1. **RF-2/D2**: `ScheduledPost` não tem `workspace_id`; o workspace vem da `socialAccount`. O job exige que o **autor** do post tenha um `Access` no workspace da conta (consulta em `accesses` do usuário), e não o `currentAccess`, porque o usuário pode ter trocado de workspace depois de agendar. Sem acesso (saiu do workspace ou conta de outro workspace) => `ScheduledPostException::accountNotFound()`, sem publicar.
2. **State**: payload cifrado com `workspace_uuid`, `user_id`, `nonce` e `exp` + nonce de uso único no cache (TTL 10 min), como no plan; compatível com os helpers `oauthStateOpen/Seal`.
3. **Agendar com várias contas** em que alguma é de outro workspace: tudo ou nada (erro `no_account_linked` e nada criado).
4. **Iniciador que saiu do workspace** antes do callback: o callback falha com `error=` e nada é criado.
5. **Expiração**: testada pelo efeito (9 min vale, 11 min não).
