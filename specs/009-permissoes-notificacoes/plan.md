# Plan 009 — Permissões da aba de notificações

Spec: [`spec.md`](spec.md) (aprovada). Questão em aberto 1 resolvida pela recomendação da spec: **perfis existentes recebem as duas permissões** (preserva o acesso atual; o dono restringe no editor de perfis).

## 1. Decisões

| Decisão | Escolha | Alternativa descartada |
|---|---|---|
| Onde proteger | `->middleware('permission:notifications.view')` / `notifications.update` nas duas rotas de `routes/api.php`, como o billing | `$permissionGroup` no controller: ele não herda `BaseController`, exigiria mudar a hierarquia |
| Perfis existentes | Migration de dados que cria as duas `Permission` (se faltarem) e as concede a **todos** os perfis | Só ao seeder: produção não roda seeder a cada deploy e os perfis antigos ficariam sem acesso |
| Migration vs. seeder | Os dois: `PermissionSeeder` (instalação nova/testes) e migration idempotente (bancos existentes) | Comando artisan manual: fácil de esquecer |
| Permissão no workspace | Avaliada pelo middleware `permission:` no workspace corrente (regra geral); super admin ignora | Permissão global: contraria o modelo |

> **Atualização 2026-10-18:** a migration de dados descrita abaixo foi descartada (banco recriado com `migrate:fresh --seed`). Vale só o seeder. Ver Divergências em `tasks.md`.

## 2. Modelo de dados
Sem tabela nem coluna nova. Dados: 2 linhas em `permissions` e N em `role_permission`.

Migration `2026_10_18_000000_grant_notification_permissions_to_roles.php`:
- `firstOrCreate` das duas permissões (mesmos `name`/`description`/`group` do seeder, incluindo `uuid` se o model gerar na criação — usar o model `Permission`).
- Para cada perfil, `syncWithoutDetaching` das duas ids (cobre perfis de todos os workspaces: `Role::withoutGlobalScopes()`).
- Idempotente; SQL via Eloquent (compatível com PostgreSQL e SQLite).
- `down()`: não remove nada (evita apagar concessões feitas depois).

## 3. Arquivos
- `database/seeders/PermissionSeeder.php`: grupo `notifications` com as duas permissões.
- `database/migrations/2026_10_18_000000_grant_notification_permissions_to_roles.php` (novo).
- `routes/api.php`: middleware nas duas rotas de preferências; comentário do bloco atualizado ("preferências exigem permissão; recebimento é livre").
- `app/Http/Controllers/Api/NotificationPreferenceController.php`: só docblock Scribe (`**Permissão:** ...`, resposta 403).
- Testes: `tests/Feature/Notifications/NotificationApiTest.php` (ajuste) + novo `NotificationPermissionTest.php`.
- Spec 007: nota remetendo à 009.
- Front (`pandypost_ui`, repo separado): `useSettingsSections.ts` (aba visível com `notifications.view`), rota da aba com redirecionamento, `NotificationPreferences.vue` com controles desabilitados sem `notifications.update`.

## 4. Efeitos colaterais
- `GET /api/permissions` e o editor de perfis passam a listar as duas novas.
- Testes existentes de preferências usam `createUserWithoutPermissions()` e passarão a receber 403: são ajustados para dar as permissões ao usuário (não é enfraquecer teste; o requisito mudou). O 403 passa a ser coberto no teste novo.
- Perfis criados por `RoleService` com lista explícita não ganham as permissões automaticamente (comportamento normal do editor).
- Nenhum impacto em envio, fila, broadcast ou e-mail (RF-3).

## 5. Testes (`NotificationPermissionTest.php`)
- Sem `notifications.view`: GET 403. Sem `notifications.update`: PATCH 403 e **nada muda** em `users.notification_preferences`.
- Só `view`: GET 200, PATCH 403. Com `update`: PATCH 200.
- Super admin acessa sem a permissão.
- Permissão avaliada no workspace corrente: com `update` em outro workspace e sem no corrente → 403.
- **Recebimento livre:** usuário sem as permissões vê `GET /api/notifications`, `unread`, `read`, `read-all`, `DELETE` (200) e continua recebendo notificação (`send` cria a linha).
- Seeder cria as duas permissões; perfil admin de workspace novo as contém; `GET /api/permissions` as lista.
- Migration: roda sobre perfil existente sem as permissões e o deixa com elas, sem duplicar na segunda execução.

## 6. Riscos
- Esquecer a migration em produção deixaria perfis antigos sem acesso: mitigado por ser migration (roda no deploy), não seeder.
- Usuário sem permissão não consegue desligar e-mails: aceito por decisão do produto; o recebimento segue com os padrões. Ponto de atenção para LGPD/opt-out de e-mail (apontamento, fora de escopo).
- Front antigo recebendo 403 na aba: tratar o 403 com mensagem clara enquanto o front novo não sobe.
