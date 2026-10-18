# 008 — Convites em tempo real

## Contexto
A UI precisa atualizar as grids de convites (enviados/recebidos) sem recarregar. O destinatário já é avisado por `WorkspaceInviteNotification` (broadcast em `App.Models.User.{id}`); falta avisar o workspace remetente quando um convite é criado, aceito, recusado ou expira.

## Escopo
Evento de broadcast `InviteUpdated` disparado pelo `InviteObserver`.

## Requisitos
- RF-1: criar convite emite `created`.
- RF-2: mudança de `status` emite `accepted`, `declined` ou `expired` (minúsculo).
- RF-3: canal `private workspaces.{uuid}` do workspace remetente.
- RF-4: payload só com `action` e `invite_uuid` (sem e-mail, o canal é de todos os membros).

## Contrato
Evento `.InviteUpdated` → `{ action: 'created'|'accepted'|'declined'|'expired', invite_uuid }`.

## Critérios de aceite
Teste Pest `tests/Feature/Invites/InviteBroadcastTest.php` cobre RF-1 a RF-4.

## Fora de escopo
`update()` em massa (revogação no accept, `invites:clean-expired`) não passa pelo observer e não emite evento.

## Adendo — leitura da notificação
- RF-5: `accept` e `decline` marcam como lidas as notificações `workspace_invite` do usuário com aquele `context.invite_uuid` (`NotificationService::markInviteAsRead`). Teste: `tests/Feature/Invites/InviteNotificationReadTest.php`.


## Adendo — remetente notificado
- Além do evento em tempo real, quem enviou o convite recebe notificação na central (`workspace_invite_accepted` / `workspace_invite_declined`, spec 007) com `action_url` `/settings/invites`.
