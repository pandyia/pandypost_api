# Tasks 009 — Permissões da aba de notificações

Legenda: `[x]` feito · `[ ]` pendente.

## Fase vermelha (agente `tdd`)
- [x] `tests/Feature/Notifications/NotificationPermissionTest.php` conforme plan §5 — falha pelo motivo certo (hoje as rotas não exigem permissão e as permissões não existem)

## API
- [x] `PermissionSeeder`: grupo `notifications` (`notifications.view`, `notifications.update`) — teste: seeder cria as duas; `GET /api/permissions` as lista
- [-] Migration `grant_notification_permissions_to_roles` — DESCARTADA por decisão (ver Divergências)
- [x] `routes/api.php`: `permission:notifications.view` no GET e `permission:notifications.update` no PATCH — teste: 403 sem permissão, banco inalterado; super admin passa
- [x] Ajustar `NotificationApiTest` (preferências) para conceder as permissões — teste: suíte de Notifications verde
- [x] Garantir que o recebimento segue livre — teste: rotas `/api/notifications*` 200 sem as permissões
- [x] Docblock Scribe do `NotificationPreferenceController` (agente `docs`) — teste: `scribe:generate` sem erro
- [x] Nota na spec 007 apontando para a 009
- [x] Suíte completa + `pint --dirty` + `octane:reload` (nunca `horizon:terminate`)

## Revisão
- [x] `qa` valida contra os critérios de aceite
- [x] `security` (área crítica de permissões): 403 em todos os caminhos, ausência de escalada, migration

## Front (`pandypost_ui`, spec própria 009 do front após esta fase)
- [ ] Aba "Notificações" visível só com `notifications.view`; rota redireciona sem a permissão
- [ ] Controles desabilitados sem `notifications.update`; 403 tratado com mensagem pt-BR
- [ ] `vue-tsc` sem erros novos e build

## Divergências
- Teste "avalia a permissão no workspace corrente" falhava por defeito de setup (`test_token` no `update` e guard guardando o usuário entre requisições). Corrigido no teste com `User::whereKey(...)->update` e `forgetGuards()`; asserções intactas.
- Auditoria de segurança: VULN-09 (Baixa, anterior à 009 e comum às rotas `permission:*`) registrada em `specs/002-security-hardening`, com teste `skip` em `tests/Feature/Security/NotificationPermissionSecurityTest.php`; fora do escopo desta spec.
- Migration de concessão aos perfis existentes descartada por decisão do usuário (2026-10-18): o banco será recriado com `migrate:fresh --seed` (o `UserSeeder` dá todas as permissões aos perfis e o `PermissionSeeder` cria as novas). Arquivo e os testes que dependiam dele foram removidos. **Risco aceito:** ambiente com dados que não passe por `fresh` terá perfis antigos sem as permissões (a aba some) até que sejam concedidas no editor de perfis ou por comando.
