# Tasks 007

- [x] 1. Migrations `notifications` e `users.notification_preferences` — teste: tabela/colunas usadas nos demais
- [x] 2. Model `Notification`, `User::notifications()`, preferências no `User` — teste: preferências
- [x] 3. Enum `NotificationType` + `family()`; `NotificationError`/`NotificationException`
- [x] 4. `CentralNotification`, canal próprio, view de e-mail, `NotificationFactory`
- [x] 5. `NotificationService` (send + notify* + consultas + preferências) — teste: ganchos, idempotência, falha segura
- [x] 6. Resources, FormRequests, controllers, rotas — teste: API (feliz/422/401/404/isolamento)
- [x] 7. Ganchos: PublishPostJob, ProcessStripeWebhookJob, YouTubeService, InviteService
- [x] 8. Comando `notifications:prune` + schedule — teste: retenção
- [x] 9. Pint + suíte completa

## Divergências

- **Destinatário do convite (bug pré-existente corrigido no escopo):** `InviteService::sendInvite` buscava o convidado com `User::findByEmail`, que passa pelo global scope de workspace (só enxerga membros do workspace corrente), logo nunca achava um usuário de fora e o aviso nunca saía. Agora busca com `withoutGlobalScopes()`. Efeito: a checagem `alreadyMember` também passa a funcionar.
- **`subscription_canceled`:** não há gancho existente (`HandleStripeWebhook` só despacha eventos de invoice/checkout). Tipo, factory e `NotificationService::notifyBilling()` existem e são testados, mas nenhum evento o dispara na v1. Ligar exige tratar `customer.subscription.deleted`.
- **Filtro `workspace_uuid`:** não implementado em `GET /notifications` (D-1: o escopo é o workspace corrente; filtro por `workspace_uuid` ficou redundante).
- **`post_published`:** fora da v1 (D-4).
- **`action_url`:** `/settings/invites`, `/pipeline` (post_failed; sem detalhe de post no UI, `post_uuid` fica no context), `/billing` e `/settings/workspace` (conta social), conforme as rotas do front.
- **Fila:** notificações usam a fila default (nenhuma `onQueue`), sem mexer no Horizon.
- **Social disconnected:** gancho só no `YouTubeService` (único ponto citado na spec); `SocialAccount::refreshYouTubeToken/TikTok` não notificam.
- **Pint:** `--dirty` reformatou trechos antigos de `InviteService.php` e `YouTubeService.php` (só estilo).
- **Classe base:** `WorkspaceInviteNotification` passa a estender `CentralNotification` (uma única classe para os tipos novos, em vez de uma por tipo).

## Adendo: post_published (opt-in)

- [x] `post_published` no enum/filtro/preferências (`post_published`, padrão false; GET/PATCH `/notification-preferences`)
- [x] Gancho central em `ScheduledPost::booted` (`updated` com status -> published), via `NotificationService::notifyPostPublished` (autor, in-app, dedupe `post_published:<uuid>`)
- [x] Testes em `NotificationHooksTest` e `NotificationApiTest`
- Divergência: substitui a nota "post_published fora da v1" acima.
