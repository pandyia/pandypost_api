# Plano 007 — Central de notificações (backend)

Segue `spec.md` + "Decisões (2026-10-17)" (D-1..D-6), que prevalecem sobre RFs conflitantes.

## Decisões

- **Workspace corrente** = `auth()->user()->resolveCurrentAccess()->workspace_id` (mesmo `access_id` usado por `BelongsToWorkspace` e `InviteService`). Não há header; troca via `POST workspaces/{uuid}/switch`.
  - Alternativa descartada: header `X-Workspace` (não existe no projeto).
- **Tabela `notifications`** padrão Laravel + `workspace_id` (nullable, FK cascade, índice) + `dedupe_key` (único por notifiable). Model `App\Models\Notification extends DatabaseNotification`; `User::notifications()` aponta para ele.
  - O `DatabaseChannel` nativo não grava colunas extras: canal próprio `WorkspaceDatabaseChannel` (estende o nativo, só sobrescreve `buildPayload`).
- **Uma classe `CentralNotification`** (ShouldQueue, fila default) para os tipos novos; `WorkspaceInviteNotification` a estende e mantém o e-mail próprio. Conteúdo (título/mensagem/action_url/context) é montado em `NotificationFactory`.
  - Alternativa descartada: uma classe por tipo (repetição sem ganho).
- **Preferências**: coluna JSON `users.notification_preferences` (`{invite,posts,social_accounts}`; ausente = ligado). `billing` não configurável.
- **Destinatários por permissão** (query direta em `accesses` + `role_permission`): posts = `posts.*`; billing = `billing.*`.
- **Idempotência**: `dedupe_key = tipo:recurso` conferida no `send()` + índice único.
- **Falha nunca derruba o fluxo**: `NotificationService::notify*` e `send` com try/catch + `Log::warning`.
- **Escopo da listagem**: `workspace_id = corrente OR type = workspace_invite`.
- Erros: `NotificationError`/`NotificationException` (`notFound` 404).

## Arquivos

Migrations (2), `Notification` model, `CentralNotification`, `WorkspaceDatabaseChannel`, `NotificationType` (+ `family()`), `NotificationFactory`, `NotificationService` (envio + consulta + preferências), `NotificationController`, `NotificationPreferenceController`, FormRequests (`ListNotificationsRequest`, `UpdateNotificationPreferencesRequest`), `NotificationResource`, `NotificationPreferenceResource`, view `emails/notification`, comando `notifications:prune`, rotas, ganchos (`PublishPostJob`, `ProcessStripeWebhookJob`, `YouTubeService`, `InviteService`).

## Testes

`tests/Feature/Notifications/`: API (happy, 422, 401, isolamento entre usuários e workspaces, 404 sem alterar banco), preferências, ganchos (post_failed, payment_failed + idempotência, invite, social), falha no notify não derruba, prune. Mail/Broadcast com `Notification::fake()`/`Event::fake` onde preciso.

## Adendo: post_published

Opt-in: a preferência decide se a notificação é criada (não só o e-mail). Gancho único no evento `updated` do model `ScheduledPost` (as 4 rotas de publicação usam `update(['status'=>'published'])`); alternativa descartada: editar cada serviço de rede.
