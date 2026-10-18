# 009 — Permissões da aba de notificações

## Contexto
A aba "Notificações" em Configurações (preferências de e-mail e aviso no app por família, `GET/PATCH /api/notification-preferences`) hoje não tem permissão: qualquer usuário autenticado lê e altera as próprias preferências. Queremos que **só quem tem a permissão** veja e edite a aba.

**Receber notificações continua livre**: sino, central (`/api/notifications*`), tempo real e e-mails seguem sem permissão e valendo os padrões/preferências já salvos. A permissão controla apenas o **acesso à aba de preferências**.

## Spec reversa (comportamento atual)
- `NotificationPreferenceController` (`show`, `update`): sem `$permissionGroup`/`$permissionMethods`; rotas em `routes/api.php` (linhas 212-213) só com autenticação. Preferências são **por usuário** (`users.notification_preferences`), não por workspace.
- `PermissionSeeder` não tem grupo `notifications`. `Role::adminPermissions()` = todas as permissões; workspace novo cria o perfil admin com todas (`WorkspaceService`, `syncWithoutDetaching`). `MeResource` expõe as permissões do usuário (super admin recebe todas).
- Perfis **já existentes** no banco não ganham permissão nova só por rodar o seeder (o seeder apenas cria a `Permission`).

## Escopo
Duas permissões novas, grupo `notifications`, e a proteção das rotas de preferências.

## Requisitos
- **RF-1** `PermissionSeeder` cria `notifications.view` ("Visualizar preferências de notificações") e `notifications.update` ("Alterar preferências de notificações"), grupo `notifications`.
- **RF-2** `GET /api/notification-preferences` exige `notifications.view`; `PATCH` exige `notifications.update`. Sem a permissão: **403**. Super admin ignora (regra geral).
- **RF-3** As rotas de recebimento (`/api/notifications`, `/unread`, `read`, `read-all`, `DELETE`) **não** mudam e continuam sem permissão. O envio (`NotificationService::send`, e-mail, broadcast) não consulta essas permissões.
- **RF-4** Perfis **novos** de workspace já nascem com as permissões (via `adminPermissions()`, sem mudança de código). Perfis **existentes** precisam receber as permissões: ver Questões em aberto 1.
- **RF-5** Front: a aba "Notificações" em Configurações só aparece com `notifications.view`; os controles ficam desabilitados sem `notifications.update`; a rota da aba redireciona sem a permissão (`hasPermission('notifications.view')`).

## Contrato de API
| Rota | Permissão | Sem permissão |
|---|---|---|
| `GET /api/notification-preferences` | `notifications.view` | 403 |
| `PATCH /api/notification-preferences` | `notifications.update` | 403 |
Contrato de resposta inalterado. `GET /api/permissions` passa a listar as duas novas (o editor de perfis as mostra).

## Critérios de aceite
- Usuário sem `notifications.view` recebe 403 no GET; sem `notifications.update`, 403 no PATCH e **nada muda no banco**.
- Com `view` mas sem `update`: lê, não altera.
- Super admin acessa sem a permissão.
- Usuário sem as permissões **continua recebendo** notificação (sino, central, broadcast) e e-mail conforme os padrões; as rotas de recebimento seguem 200.
- Perfil admin de workspace novo contém as duas permissões; `GET /api/permissions` as lista.
- Testes em `tests/Feature/Notifications/` (happy path, 403, receber continua livre). Isolamento entre workspaces não se aplica (preferência é do usuário), mas o teste confirma que a permissão é avaliada no workspace corrente.

## Fora de escopo
Preferência global do workspace ou do sistema; mudar o recebimento; mudar o que cada família faz.

> **Atualização 2026-10-18:** RF-4 para perfis existentes não terá migration; o banco será recriado com `fresh --seed`.

## Questões em aberto
1. **Perfis existentes:** como conceder `notifications.view/update` aos perfis que já existem? (a) migration de dados que dá as duas permissões aos perfis que já têm `settings.view`/`settings.update` (equivalência: quem já mexia em configurações continua mexendo); (b) a todos os perfis, para não mudar o comportamento hoje; (c) a nenhum — cada dono concede no editor de perfis. Recomendação: **(b)**, pois preserva o acesso atual e o dono restringe depois.
2. Hoje todo usuário edita as próprias preferências; com (b), ninguém perde acesso até o dono revogar. Confirmar que isso é o desejado.
