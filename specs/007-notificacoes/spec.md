# Spec 007 — Central de notificações (adaptação da arquitetura do SRGA-Backend)

**Status:** decisões aprovadas em 2026-10-17 — implementação autorizada (backend + frontend) · **Front:** a definir

## Contexto

Hoje o Pandypost **não tem central de notificações**. O mais próximo disso é a tabela `invites`: o front consulta `GET /api/invites/received` para saber "se tem algo para mim". Isso funciona só para convites; qualquer outro aviso (post que falhou, pagamento recusado, conta social desconectada) não chega ao usuário a não ser pelo log ou pelo status do recurso.

O SRGA-Backend já resolveu isso com o mecanismo nativo do Laravel (`database` + `mail` + `broadcast`). Esta spec descreve como trazer essa arquitetura para cá, respeitando as regras do Pandypost (sem Repository, multi-tenant por workspace, rotas por `uuid`).

### Referência: como o SRGA faz (lido em `SRGA-Backend`)

| Peça | SRGA | Arquivo |
|---|---|---|
| Tabela | `notifications` padrão do Laravel: `id` uuid, `type`, `notifiable` (morphs), `data` (JSON em texto), `read_at`, timestamps | `database/migrations/2024_01_01_000000_create_notifications_table.php` |
| Model | `Notification extends DatabaseNotification` com casts `data` → array e `read_at` → datetime | `app/Models/Notification.php` |
| Notification | Classe por evento (`SampleInactivationNotification`), `via: ['database','mail','broadcast']`; `toArray()` define o payload (`type`, `title`, `message`, `action_url` + contexto); `toMail()` usa view própria; `toBroadcast()` reaproveita o `toArray()` | `app/Notifications/` |
| Disparo | Service chama `$user->notify(...)` para cada destinatário (gestores/ADM ao criar, solicitante ao analisar) | `SampleInactivationRequestService::notifyGestoresAndAdms/notifyRequester` |
| API | `GET notifications` (paginado, filtro por `notifiable_id` = usuário logado), `GET notifications/unread`, `POST notifications/{id}/read`, `POST notifications/read-all` | `NotificationController`, `routes/api.php` |
| Resposta | `NotificationResource` = `id` + `read_at` + conteúdo do `data` achatado; `NotificationCollection` acrescenta `unread_count` | `app/Http/Resources/` |
| Tempo real | Broadcast no canal privado `App.Models.User.{id}` (Reverb) | `routes/channels.php` |
| Camadas | Controller → Service → Repository (`getUnreadForUser`, `markAsRead`, `markAllAsRead` sempre com `where notifiable_id = Auth::id()`) | `NotificationService`, `NotificationRepository` |

**Pontos do SRGA que NÃO devemos copiar** (apontamentos):

- `$request->all()` direto no `paginate` do controller (proibido aqui; usar FormRequest/`validated()`).
- Rotas com o `id` da notificação exposto (aqui: `uuid`).
- `markAsRead` com `$id` solto, sem FormRequest.
- `via` fixo com `mail` para tudo, sem preferência do usuário e sem `ShouldQueue` (aqui: fila + preferência).
- `notifiable_id` inteiro com `morphs`: no Pandypost a notificação pertence ao `User`, mas precisa saber **de qual workspace** veio.

### Estado atual no Pandypost (spec reversa)

- `Notifiable` já está no `User`. Existe `NotificationService::send(User, NotificationType, array $payload)` + `NotificationFactory` + enum `NotificationType` (`email_verification`, `password_reset`, `workspace_invite`). As três notificações atuais são **só e-mail** (`via: ['mail']`), algumas `ShouldQueue`.
- Não existe tabela `notifications`, nem model, nem rotas, nem Resource.
- `routes/channels.php` já registra `App.Models.User.{id}` e `workspaces.{uuid}` (Reverb). Só `RoleEvent` faz broadcast hoje.
- Convite: `InviteService::sendInvite()` cria o `Invite` e, se o convidado já tem conta, envia o e-mail `WorkspaceInviteNotification`. O front depende de `GET /api/invites/received` para exibir convites pendentes.
- Eventos que **deveriam** avisar e hoje não avisam:
  - `PublishPostJob::failed()` só grava `status=failed` e `error_message`; sucesso na publicação não avisa ninguém.
  - `ProcessStripeWebhookJob` trata `invoice.payment_failed` apenas gravando o `Payment` como `FAILED`.
  - `YouTubeService` lança exceção quando o token expira sem `refresh_token`; a conta fica quebrada sem aviso.

## Decisões (2026-10-17) — substituem RF/questões conflitantes

- **D-1 Workspace manda:** a central é **por workspace**. `GET /api/notifications` lista só as do workspace corrente do usuário; notificação de um workspace nunca aparece em outro. `workspace_uuid` de outro workspace/que não é membro → lista vazia. Exceção única: `workspace_invite` (convidado ainda não é membro) é do usuário, `workspace_id` = workspace que convida, e fica visível em qualquer workspace.
- **D-2 Destinatários por permissão:** `post_failed` vai para **todos os membros do workspace com permissão de ver/gerenciar posts**, independente de quem postou; o `context` informa o autor (`author_name`). Billing (`payment_failed`, `subscription_canceled`) vai para os membros com permissão de billing do workspace.
- **D-3 Preferências entram na v1:** e-mail por família (`invite`, `posts`, `social_accounts`) ligável/desligável; `database` e `broadcast` sempre ligados. **Billing não é configurável**: sempre e-mail + database + broadcast.
- **D-4 Eventos v1:** `workspace_invite`, `post_failed`, `payment_failed`, `subscription_canceled`, `social_account_disconnected`. `post_published` existe como **opt-in** (preferência `post_published`, padrão desligado; decide se a notificação é criada; só o autor do post, só in-app, sem e-mail). Fora da v1: `subscription_expiring`.
- **D-5 Broadcast** só em `App.Models.User.{id}`. **Retenção:** lidas há mais de 30 dias são apagadas por comando agendado; não lidas nunca.
- **D-6** `notifiable` sempre `User`. `EmailVerification`/`PasswordReset` ficam só e-mail, fora da central. Convidado sem conta: sem notificação na v1.

## Escopo

1. Persistir notificações por usuário (tabela `notifications` do Laravel) e expô-las por API.
2. Entregar em tempo real (Reverb) e, conforme preferência, por e-mail.
3. Quatro famílias de evento na v1: **convite recebido**, **post publicado/falhou**, **billing** e **conta social desconectada**.
4. Migrar o aviso de convite para a central, mantendo o convite como entidade.

## Requisitos

### Armazenamento e modelo

- **RF-1** Tabela `notifications` no formato padrão do Laravel (`id` uuid, `type`, `notifiable`, `data`, `read_at`, timestamps) mais a indicação do **workspace de origem**, para filtrar por workspace e exibir o contexto no front. `workspace_id` e o `uuid` de recurso nunca vêm do request.
- **RF-2** Model `Notification` estendendo `DatabaseNotification` (casts de `data` e `read_at`), como no SRGA. A identificação externa da notificação é o próprio `id` uuid (já é uuid, não expõe sequência).
- **RF-3** Toda notificação tem `data` com chaves fixas: `type`, `title`, `message`, `action_url` (rota do front) e `context` (ids públicos `uuid` dos recursos envolvidos). `data` **nunca** contém tokens de plataforma, `$e->getMessage()` de terceiro nem dados de cartão.

### Canais

- **RF-4** Cada Notification declara `via` conforme o evento e a preferência do usuário: `database` (sempre), `broadcast` (sempre), `mail` (conforme preferência — ver RF-12).
- **RF-5** Notifications implementam `ShouldQueue` (fila padrão de notificações; **não** a fila de plataforma, que é de publicação).
- **RF-6** Broadcast no canal privado `App.Models.User.{id}` (já autorizado em `routes/channels.php`). Evento com o mesmo payload do `data` + `id`.
- **RF-7** E-mails mantêm o layout `resources/views/emails/layout.blade.php`.

### Eventos da v1

| Tipo (`NotificationType`) | Quando | Destinatário (proposto) | `action_url` |
|---|---|---|---|
| `workspace_invite` (já existe) | convite criado para usuário **com conta** | convidado | tela de convites |
| `workspace_invite_accepted` | convite aceito (`InviteService::accept`) | quem enviou o convite (se ainda for membro); família `invite`, in-app + e-mail com layout próprio | `/settings/invites` |
| `workspace_invite_declined` | convite recusado (`InviteService::decline`) | quem enviou o convite (se ainda for membro); família `invite`, in-app + e-mail com layout próprio | `/settings/invites` |
| `post_published` | `PublishPostJob`/serviço da rede conclui com sucesso | autor do post (`user_id`) | detalhe do post |
| `post_failed` | `PublishPostJob::failed()` | autor do post e membros com permissão de gerenciar posts no workspace (a confirmar) | detalhe do post |
| `payment_failed` | `invoice.payment_failed` | dono do workspace (a confirmar) | tela de assinatura |
| `subscription_canceled` / `subscription_expiring` | cancelamento / proximidade do fim do período (a confirmar o gatilho de "expirando") | dono do workspace | tela de assinatura |
| `social_account_disconnected` | token expirado/revogado sem `refresh_token`, ou erro de permissão da plataforma | usuário que conectou a conta | tela de contas sociais (reconectar) |

- **RF-8** O disparo vive no **Service** (ou Listener/Observer onde já existe o gancho), nunca no Controller. Os services chamam `NotificationService::send()` (já existente), que passa a aceitar os novos tipos via `NotificationFactory`.
- **RF-9** Falha ao notificar **nunca** derruba o fluxo principal: se o `notify` falhar, o post/webhook/convite segue normal (log do erro).
- **RF-10** Sem duplicidade: o mesmo evento (ex.: webhook reentregue pelo Stripe, job com `tries`) não gera duas notificações para o mesmo destinatário. Chave de idempotência por tipo + recurso + destinatário (ex.: `post_failed` + post).
- **RF-11** O convite continua sendo a entidade `Invite` (aceitar/recusar). A notificação só **aponta** para ele; `GET /api/invites/received` permanece.

### Preferências

- **RF-12** Cada usuário pode ligar/desligar o **e-mail** por família de evento. `database` e `broadcast` não são desligáveis na v1. **Eventos transacionais** (`email_verification`, `password_reset`) continuam só por e-mail e fora da central. *(Depende da questão em aberto 3.)*

### API

Todas as rotas são do **usuário logado** (escopo `notifiable = usuário`), sem permissão de grupo (equivalente a `profile`/`me`), e **toda a lógica no `NotificationService`**, sem Repository.

- **RF-13** `GET /api/notifications` — paginada, mais recentes primeiro. Filtros: `read` (`all|unread|read`), `type`, `workspace_uuid`. A resposta inclui `unread_count` (como `NotificationCollection` do SRGA).
- **RF-14** `GET /api/notifications/unread` — não lidas, com `total_unread`.
- **RF-15** `PATCH /api/notifications/{notification}/read` — marca uma como lida (idempotente).
- **RF-16** `PATCH /api/notifications/read-all` — marca todas como lidas (opcionalmente só as de um `workspace_uuid`).
- **RF-17** `DELETE /api/notifications/{notification}` — remove uma notificação do usuário.
- **RF-18** Notificação de **outro usuário** responde 404 e não muda nada no banco.
- **RF-19** `GET/PATCH /api/notification-preferences` — lê e altera a preferência de e-mail por família (RF-12).
- **RF-20** Retenção: notificações lidas há mais de N dias são removidas por comando agendado (valor de N em aberto).

## Contrato de API

`GET /api/notifications?read=unread&per_page=15`

```json
{
  "data": [
    {
      "id": "0c2f1d8e-....",
      "type": "post_failed",
      "title": "Falha ao publicar",
      "message": "Não foi possível publicar \"Meu vídeo\" no YouTube.",
      "action_url": "/pipeline",
      "context": { "post_uuid": "6f1a....", "platform": "youtube", "workspace_uuid": "58d7...." },
      "read_at": null,
      "created_at": "2026-10-16 10:00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 },
  "unread_count": 1
}
```

- Escrita: `{"message": "Notificação marcada como lida.", "data": { ... }}`.
- Mensagens em pt-BR; erros de domínio via `NotificationError` + `NotificationException` (ex.: `notFound()`).
- Docs Scribe pelo agente `docs`.

## Critérios de aceite

1. Convidar um usuário **com conta** cria 1 notificação `workspace_invite` para ele e dispara o broadcast; o e-mail segue a preferência. Convidar e-mail **sem conta** continua só enviando e-mail.
2. Post falhando definitivamente cria `post_failed` para o autor; reexecução do `failed()` não duplica.
3. Post publicado com sucesso cria `post_published`.
4. `invoice.payment_failed` cria `payment_failed` para o dono do workspace; o mesmo webhook entregue duas vezes não duplica.
5. Erro de token expirado em conta social cria `social_account_disconnected` para quem conectou a conta, sem expor o erro da plataforma.
6. `data` de nenhuma notificação contém token, `access_token`, `refresh_token` nem mensagem crua de exceção de terceiros.
7. `GET /api/notifications` só lista notificações do usuário logado; `unread_count` correto; filtros `read`, `type`, `workspace_uuid` funcionam.
8. Marcar uma/todas como lida muda só as do usuário logado. Notificação de outro usuário → 404 e **nada muda no banco**.
9. Usuário desliga e-mail da família `billing` → próximo `payment_failed` cria `database`+`broadcast` e **não** envia e-mail.
10. Falha no `notify` (ex.: broker de e-mail fora) não impede a publicação, o processamento do webhook nem o convite.
11. Usuário não autenticado → 401 em todas as rotas.
12. **Isolamento entre workspaces:** usuário membro de dois workspaces recebe notificações dos dois; o filtro `workspace_uuid` de um workspace do qual **não** é membro retorna lista vazia (sem vazar dados).

## Fora de escopo

- Notificações push de navegador/mobile (Web Push/FCM).
- Notificação por WhatsApp/Slack.
- Central de notificações para o **super admin** (eventos de plataforma).
- Agrupamento/digest (ex.: "5 posts publicados hoje").
- Reescrever o fluxo de convite (aceitar/recusar) ou remover `GET /api/invites/received`.
- Notificações de comentários/aprovação (dependem de features ainda inexistentes).
- Tradução para outros idiomas.

## Questões em aberto

1. **Destinatários:** `post_failed` vai só para o autor, ou também para quem tem permissão de ver/gerenciar posts no workspace? Billing vai só para o dono do workspace ou para todos com `billing.*`?
2. **"Assinatura expirando":** qual gatilho? Webhook `customer.subscription.updated` com `cancel_at_period_end`, ou um comando diário que olha o fim do período? (Hoje o webhook não trata esses eventos.)
3. **Preferências na v1:** entram agora (RF-12/RF-19) ou a v1 manda e-mail sempre e as preferências ficam para a spec seguinte?
4. **Retenção (RF-20):** quantos dias para as lidas? E para as não lidas, nunca apaga?
5. **Broadcast:** só no canal do usuário (`App.Models.User.{id}`) ou também no canal do workspace (`workspaces.{uuid}`) para atualizar listas de posts em tempo real?
6. **Convite:** quando o convidado não tem conta, a notificação deve ser criada no cadastro (quando ele passar a existir) para aparecer na central?
7. **Notificação de sucesso de post:** em contas que agendam muitos vídeos, `post_published` pode virar ruído. Entra na v1 ou fica atrás de preferência?
8. **Modelo da notificação:** `notifiable` sempre `User` (como definido) ou também `Workspace` (notificação "da equipe")? A spec assume só `User`.
9. **Migração dos e-mails existentes:** `WorkspaceInviteNotification` passa a ter `database`/`broadcast`; `EmailVerification` e `PasswordReset` ficam como estão (só e-mail, fora da central)?
