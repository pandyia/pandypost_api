# Plan 021 — Agendamento com envio em segundo plano

## Decisões

1. **Reservar no clique, confirmar ao terminar.** O servidor cria o post `awaiting_upload` (validado) e o front confirma com `upload-complete`. A fila de upload é do navegador (o arquivo só existe lá); a fila do Horizon continua só para publicar.
   *Descartado:* ticket em cache com a hora da emissão do upload (resolve o 422, mas o usuário continua preso esperando o envio e não há como avisar "enviando").
2. **Colisão evitada pelo path.** Cada post nasce amarrado ao `media_storage_path` (UUID gerado pelo servidor, dentro do workspace). `upload-complete` só libera posts desse path no tenant corrente.
3. **Status novo** `awaiting_upload` em `ScheduledPostStatus`; coluna `upload_completed_at` nullable.
4. **Idempotência** com `lockForUpdate` e checagem do status dentro da transação, mesmo padrão de `retry()`. Job com `->afterCommit()`.
5. **Aviso em três camadas:** resposta de `upload-complete` (popup na aba que enviou), notificação `post_upload_late` pelo sino (CentralNotification já faz broadcast Reverb) e evento `ScheduledPostUpdated` no canal do workspace para listas ao vivo.
6. **Expiração** por comando agendado (molde `CloseStuckPostsCommand`), texto fixo em pt-BR no enum de erro.
7. **Config única** `config/scheduling.php`.

7. **`StoragePathRule`** ganha modo sem exigir existência no S3, usado só quando `media_pending=true` (formato e prefixo do workspace seguem validados). Existência é conferida em `upload-complete`.
8. **Listagem**: `status` entra em `$normalFilter` do `ScheduledPostService`.

## Modelo de dados
Migration única: `scheduled_posts.upload_completed_at` timestamp nullable (SQLite e PostgreSQL).

## Arquivos (API)
- `config/scheduling.php` (novo)
- `database/migrations/*_add_upload_completed_at_to_scheduled_posts.php` (novo)
- `app/Enums/ScheduledPostStatus.php` (+ `AWAITING_UPLOAD`)
- `app/Enums/Exceptions/ScheduledPostError.php` e `app/Exceptions/ScheduledPostException.php` (`uploadNotFound`, `uploadExpired`)
- `app/Http/Requests/StoreScheduledPostRequest.php`, `RetryScheduledPostRequest.php`, `UploadCompleteRequest.php` (novo)
- `app/Http/Controllers/Api/ScheduledPostController.php` (`uploadComplete`, `$permissionMethods`), `routes/api.php`
- `app/Services/ScheduledPostService.php` (`schedule` com `media_pending`, `completeUpload`, `cancel`)
- `app/Events/ScheduledPostEvent.php` (novo, `ScheduledPostUpdated`)
- `app/Console/Commands/ExpireAwaitingUploadsCommand.php` (novo) e `routes/console.php`
- Notificação `post_upload_late`: `NotificationType`, `NotificationFactory`, `NotificationService`, preferências (agente `notifications`)
- `app/Http/Resources` do post (status novo, `upload_completed_at`)

## Arquivos (front, `../pandypost_ui`)
Há mudanças não commitadas em `scheduleService.ts`, `scheduleWizardStore.ts`, `types/schedule.ts` e `constants/schedules.ts`; o trabalho entra por cima, sem descartar nada.
- `src/store/uploadQueueStore.ts` (novo), `src/components/schedules/UploadQueueWidget.vue` (novo, montado no layout)
- `src/services/scheduleService.ts` (`reserve`, `uploadAndComplete`), `src/store/scheduleWizardStore.ts` (`submit` reserva, enfileira e fecha o modal)
- `src/composables/useSocketListeners.ts` (`useScheduledPostsListener`)
- `src/constants/schedules.ts`, `src/types/schedule.ts` (status `awaiting_upload`, chip "Enviando vídeo")

## Efeitos colaterais
Evento broadcast, notificação nova, rotina agendada nova. Nenhuma mudança no fluxo sem `media_pending`.

## Testes
Pest em `tests/Feature/ScheduledPosts/AwaitingUploadTest.php` com `Carbon::setTestNow`, `Queue::fake`, `Event::fake`, S3 fake. Casos dos critérios de aceite, 403 e isolamento (404 + banco inalterado). Segurança em `tests/Feature/Security/`. Front: `npm run build` (vue-tsc) e teste manual com Slow 3G.

## Riscos
- **Aba fechada no meio do envio:** post fica `awaiting_upload` até expirar e virar `failed` com aviso claro; o `beforeunload` reduz a chance.
- **Reload perde a fila:** o `File` não persiste; coberto pela expiração.
- **Confirmação forjada** (`upload-complete` sem upload): mitigada pela conferência no S3 e pelo escopo de tenant.
- **Área crítica (esteira de publicação):** `security` obrigatório depois do `qa`.
