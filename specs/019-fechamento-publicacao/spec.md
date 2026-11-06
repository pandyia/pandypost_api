# 019 — Fechamento da publicação (YouTube, Instagram, TikTok)

> Status: **aprovada** (pedido direto do Gabriel, 2026-11-06; decisões D1–D5 delegadas ao Claude). Área crítica (esteira de publicação, OAuth social, permissões/workspace). Depende das specs 011, 014–018.

## Contexto
Última rodada antes do teste manual de agendamento. Origem: relatório do QA (BUG-1 e divergências) e da auditoria de segurança final (`tests/Feature/Security/FinalAuditSocialPublishTest.php`, 11 achados `VULN-*` registrados em `specs/002-security-hardening/spec.md`).

## Escopo
Corrigir com teste o que o QA e a segurança acharam. Contrato de API inalterado, exceto: (a) 422 novo no agendamento de TikTok sem permissão de publicar, (b) cancelar passa a valer para qualquer membro com `posts.delete`, (c) texto da mensagem `no_account_linked`.

## Decisões do dono
- **D1** Cancelar e reenviar: qualquer membro com a permissão (`posts.delete` / `posts.create`) no workspace do post pode, não só o autor (coerente com "conta do workspace"). Outro workspace continua 404.
- **D2** Conta TikTok com `can_publish=false` → **422 no agendamento** (tudo ou nada), mensagem pt-BR "reconecte a conta do TikTok"; a publicação continua falhando de forma segura.
- **D3 (deferido, não implementar)** VULN-SA-01 (criptografar tokens: quebraria contas já conectadas sem migração de dados planejada) e VULN-UP-01 (presigned PUT: muda o contrato de upload com o front). Testes seguem em `->skip`. Pendência.
- **D4 (pendência futura, não mudar agora)** Ao desconectar a conta, os posts somem de `GET /scheduled-posts` (o escopo de tenant passa pela conta e `social_account_id` é `nullOnDelete`). Decisão futura: histórico preservado (soft delete da conta ou `workspace_id` no post).
- **D5 (fora de escopo)** `consumeQuota`/assinatura são no-op ("FASE 2"); só registrado.

## Requisitos
- RF-1 (VULN-POST-01/BUG-1) Cancelar um post só apaga a mídia se nenhum outro post pendente/processando/falho-retentável a usa (`deleteIfUnused`). `disconnect()` idem para os posts pendentes que cancela.
- RF-2 (D1) Cancelar/reenviar não filtram por autor; só pelo workspace corrente (escopo de tenant).
- RF-3 (VULN-TEN-02, Crítica) `BelongsToWorkspace` **falha fechado**: usuário autenticado sem `currentAccess` não enxerga registro de tenant nenhum. `User::resolveCurrentAccess()` não deixa a relação nula em cache. `ensureValidSocialAccount` filtra por `workspace_id` explícito. Contexto sem usuário autenticado (jobs, comandos, webhooks) continua sem escopo, como hoje.
- RF-4 (VULN-SCHED-01) `GET /social-accounts/schedules` exige `social_accounts.view` **e** `posts.view`.
- RF-5 (VULN-OAUTH-04) O callback só mostra no redirect a mensagem de `BaseException`; qualquer outra exceção vira mensagem genérica e o log traz só a classe.
- RF-6 (VULN-OAUTH-05) O `state` leva a plataforma (payload e cache do nonce); `consume` compara com a do callback.
- RF-7 (VULN-OAUTH-06) O callback exige `social_accounts.connect` do iniciador **no workspace do state**.
- RF-8 (VULN-OAUTH-07) Throttle na rota pública de callback.
- RF-9 (VULN-RETRY-03/03b) `InstagramApi::permalink` só aceita string https de host `instagram.com` (ou subdomínio); caso contrário, falha de domínio já tratada (`permalinkUnavailable`), e o post fecha como publicado sem link.
- RF-10 (VULN-RETRY-04) Logs de `PublishPostJob::failed` e `InstagramService::processContainer` sem `getMessage()` cru (classe/código).
- RF-11 (TikTok) O PUT do upload não segue redirecionamento; `CheckTikTokPostStatusJob::markFailed` não sobrescreve post `published`/`cancelled`; D2.
- RF-12 `no_account_linked` diz "A conta selecionada não está disponível neste workspace." (sem uuid). Remove-se o código morto `SocialAccountException::accountAlreadyLinked` e seu case.

## Contrato de API (mudanças)
- `POST /scheduled-posts`: 422 `errors.social_account_uuids` quando a conta do TikTok não pode publicar; 400 `no_account_linked` com texto novo.
- `DELETE /scheduled-posts/{uuid}`: qualquer membro com `posts.delete` (antes só o autor; não-autor recebia 404).
- `GET /social-accounts/schedules`: 403 sem `social_accounts.view` ou `posts.view`.
- Callback OAuth: novos motivos de `?error=` (mensagem genérica; "Estado OAuth inválido." para plataforma divergente; sem permissão).

## Critérios de aceite
Cada VULN corrigido tem o `->skip` removido e passa; QaConjuntoPublicacaoTest verde; suíte completa verde duas vezes; skips restantes só D3 e os antigos.

## Fora de escopo
D3, D4, D5. Tokens em texto puro e presigned PUT sem limite de tamanho seguem como risco aceito até decisão.

## Questões em aberto
Nenhuma (D1–D5 decididas).
