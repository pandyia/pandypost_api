# Tasks 017

## Fase 1 — Autorização por workspace
- [x] `ensureValidSocialAccount` sem filtro por `user_id` — teste: membro B agenda em conta do A; outro workspace → erro, banco intacto; sem permissão → 403
- [x] `PublishPostJob` valida workspace da conta vs. do post — teste: mesmo workspace com `user_id` diferentes publica; workspaces diferentes → `accountNotFound`
- [x] Documentar o novo sentido de `SocialAccount.user_id` — teste: reconexão por outro membro não quebra posts pendentes

## Fase 2 — OAuth `state`
- [x] `OAuthStateService` (issue/consume, nonce uso único, TTL 10 min) — teste: válido, replay, expirado, adulterado, sem nonce, outro workspace
- [x] Google, Instagram e TikTok emitem o `state` novo; controller consome — teste por rede
- [x] `syncAccount` grava `user_id` do iniciador — teste: callback grava o iniciador
- [x] Remover `->skip` de VULN-TT-03 e OAUTH-01; atualizar spec 002 (status dos achados)

## Fase 3 — Fechamento
- [x] Pint nos alterados + suíte completa verde
- [ ] `qa`, `security` (obrigatório), `docs` (callback e agendamento no Scribe)

## Divergências
- **D2 (job)**: `ScheduledPost` não tem `workspace_id`. O `PublishPostJob` compara com o workspace da **conta** (`social_account.workspace_id`): o autor do post precisa ter um `Access` nesse workspace (consulta em `accesses`), e não o `currentAccess`, para a troca de workspace depois de agendar não quebrar o post. Sem acesso ou sem conta: `accountNotFound()`.
- **D3 (state)**: `OAuthStateService` com `issue(User, Workspace)` e `consume(?string)`. Payload `encrypt([workspace_uuid, user_id, nonce, exp])`; nonce em cache (`oauth_state:{nonce}` com workspace e usuário, TTL 10 min, `Cache::pull` = uso único). Falhas viram `SocialAccountException::invalidOAuthState()`.
- **D4 (syncAccount)**: assinatura virou `syncAccount(User $user, ?string $code = null, ?Workspace $workspace = null)`. O `Workspace` opcional é o do state (o iniciador pode ter trocado de workspace corrente); sem ele vale o corrente. O ramo `Workspace` como contexto sumiu. `OAuthProviderInterface` e `SocialAccountService` seguem a nova assinatura.
- **Callback**: mensagem "Workspace não encontrado." foi unificada em "Estado OAuth inválido." (iniciador sem acesso ao workspace do state, workspace ou usuário inexistente).
- **Reforço do Gabriel**: acrescentados testes ponta a ponta por rede (`WorkspaceSharedPublishFlowTest`): B agenda via HTTP na conta do A, o job roda com a rede mockada e o post termina `published`; sem `posts.create` => 403. Grep de `user_id` ligado a conta social em `app/` registrado no relatório; única ocorrência que fica: `NotificationService::notifySocialAccountDisconnected` (avisa quem conectou por último, informativo).
- Testes acrescentados (além dos do tdd): autor com `currentAccess` em outro workspace publica; autor sem Access no workspace da conta falha; várias contas com uma de outro workspace => tudo ou nada; iniciador que saiu do workspace => `error=`.
