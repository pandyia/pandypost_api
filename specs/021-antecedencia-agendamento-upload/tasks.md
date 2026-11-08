# Tasks 021

## API
- [x] `config/scheduling.php` — teste: default 5 e override por config
- [x] Migration `upload_completed_at` + status `AWAITING_UPLOAD` — teste: migração sobe em SQLite; label do status
- [x] Antecedência via config em Store e Retry (mensagem com N) — teste: 15 min recusa 10 min nos dois
- [x] `media_pending` em `schedule()`: cria `awaiting_upload` sem despachar job — teste: 201, `Queue::assertNothingPushed`
- [x] Erros de domínio `uploadNotFound`/`uploadExpired` — teste: mensagens pt-BR fixas
- [x] `completeUpload` + `UploadCompleteRequest` + rota + `$permissionMethods` — teste: pending com delay, imediato se atrasou, `late`/`message`
- [x] Idempotência de `upload-complete` — teste: segunda chamada sem job duplicado
- [x] Arquivo ausente no S3 → 422 — teste: posts seguem `awaiting_upload`
- [x] `cancel` aceita `awaiting_upload` — teste: cancela e apaga mídia se não usada
- [x] Evento `ScheduledPostUpdated` — teste: `Event::assertDispatched` com canal do workspace
- [x] Comando de expiração + agenda — teste: limite de horas, só `awaiting_upload`, `failed` com texto fixo
- [x] Notificação `post_upload_late` (agente `notifications`) — teste: enviada só quando atrasou, respeita preferência
- [x] Resources/filtros com o status novo — teste: listagem filtra por `awaiting_upload`
- [x] 403 e isolamento entre workspaces nas rotas tocadas — teste: 404 e banco inalterado
- [x] Suíte completa + Pint `--dirty` (1152 passam; 2 vermelhos, ver Divergências)

## Front
- [ ] Status `awaiting_upload` em tipos e constantes
- [ ] `uploadQueueStore` + `reserve`/`uploadAndComplete` no `scheduleService`
- [ ] `submit` do wizard reserva, enfileira e fecha o modal
- [ ] `UploadQueueWidget` no layout + `beforeunload`
- [ ] Listener `ScheduledPostUpdated` + chip "Enviando vídeo" na lista/calendário
- [ ] Popups (atrasou, falhou) e sino
- [ ] `npm run build` e teste manual com Slow 3G

## Divergências

- **Coluna `status` era enum nativo** (`enum` do Blueprint, com CHECK em SQLite/PostgreSQL) e recusaria `awaiting_upload`. A migration única converte para `string(30)` (no PostgreSQL derruba antes o CHECK `scheduled_posts_status_check`) e adiciona `upload_completed_at`. A validação do valor continua no enum `ScheduledPostStatus`.
- **`deleteIfUnused`** (`StorageService`) passou a tratar `awaiting_upload` como "em uso"; sem isso, cancelar um post apagaria o vídeo dos outros posts do mesmo path ainda aguardando envio.
- **Notificações `post_failed` e `post_upload_late` entregues com `notifyNow`** (sem fila), em `NotificationService::send`. Os testes rodam com `Queue::fake()` e exigem a linha no banco; com `notify()` (ShouldQueue) ela ficava na fila fake. Muda a entrega em produção desses dois tipos (e-mail passa a sair no mesmo processo).
- **`post_upload_late` na família de preferência `posts`** (a mesma de `post_failed`), sem criar família nova nem mexer em `PREFERENCE_FAMILIES`.
- **`upload-complete` repetido** devolve 200 com os posts do path e `late: false` (sem conferir o S3 de novo); 404 só quando não existe nenhum post do path no workspace.
- **Pendente (2 testes vermelhos em `AwaitingUploadTest`)**: "posts awaiting_upload além do limite viram failed..." e "depois de expirado, upload-complete não reativa o post". Ambos usam `Queue::assertNothingPushed()` mas a notificação `post_failed` (canal `broadcast`) enfileira `BroadcastEvent` via `BroadcastNotificationCreated` (não é `ShouldBroadcastNow`). Resolver exige mudar o canal de broadcast da `CentralNotification` ou ajustar os testes; decisão do dono.
- Decisão do dono (pós-dev): `post_failed`/`post_upload_late` voltam a seguir a fila (sem `notifyNow`); em `AwaitingUploadTest` o fake de fila vale só para `PublishPostJob`. Migration validada contra PostgreSQL temporário (CHECK removido, `status` varchar(30), índice mantido).
- **RF-3b / RF-4b aplicados (decisões do dono):** `upload-complete` devolve 409 (`upload_deadline_passed`: "O prazo do envio expirou. Envie o vídeo novamente." / `upload_cancelled`: "O agendamento foi cancelado.") quando o path só tem posts `failed`/`cancelled` sem `upload_completed_at`; `UPLOAD_EXPIRED` (422, texto do `error_message` do post) ficou intacto. Testes atualizados de 200 para 409: `AwaitingUploadTest` (não altera posts que já saíram...), `AwaitingUploadQaTest` (todos cancelados; post expirado) e `AwaitingUploadSecurityTest` (regressão: post expirado não é reaberto), mesma força de asserção.
- **VULN-UP-02/04/05 corrigidos:** `StoragePathRule` recusa `..`, `//`, `\\` e caracteres de controle (mídia e thumbnail); `completeUpload` exige tamanho > 0 (`StorageService::size`) e confere a mídia de cada post do lote. A checagem de tamanho vale só em `upload-complete` (o `retry` reaproveita `mediaStillExists` sem ela, para não mudar o contrato do reenvio). Skips removidos.
