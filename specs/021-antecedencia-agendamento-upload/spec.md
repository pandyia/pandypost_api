# Spec 021 — Agendamento com envio de vídeo em segundo plano

## Contexto

O agendamento exige `scheduled_at` com antecedência mínima (hoje 5 min; em produção será 15). A regra é validada no `POST /scheduled-posts`, que só acontece depois que o vídeo terminou de subir no S3. Com internet lenta ou vídeo grande, o upload consome a janela e a API recusa (422) um agendamento que era válido no clique (clicou 16:45 para 16:50, upload terminou 16:46).

Além disso, o usuário fica preso esperando o upload antes de agendar outra coisa.

Comportamento atual (spec reversa): `StoreScheduledPostRequest` e `RetryScheduledPostRequest` usam `after:+5 minutes` fixo; `ScheduledPostService::schedule` cria os posts `pending` e despacha `PublishPostJob` com `delay(scheduled_at)` (data passada executa na hora); `cancel` só aceita `pending`; `retry` só aceita `failed`.

## Escopo

- Reservar o post no clique (validação de antecedência no clique) com status novo `awaiting_upload`.
- O front envia o vídeo ao S3 em segundo plano, numa fila no navegador, e confirma ao terminar.
- Confirmado o envio, o post vai para a fila de publicação; se o horário já passou, publica na hora.
- Avisar o usuário, em linguagem simples, que o envio está em andamento, terminou atrasado ou não terminou.
- Antecedência mínima configurável em um único lugar.

## Requisitos

- **RF-1** — `POST /scheduled-posts` aceita `media_pending: true`. Com a flag, valida tudo como hoje (inclusive `scheduled_at >= agora + antecedência`), cria os posts em `awaiting_upload` e **não** despacha job. Sem a flag, o comportamento atual não muda.
- **RF-1b** — Com `media_pending: true`, o `media_storage_path` (e a thumbnail) continuam validados quanto a formato e prefixo do workspace, mas a existência no S3 **não** é exigida no clique (o vídeo ainda está subindo); ela passa a ser conferida em `upload-complete` (RF-2/RF-4). `media_pending` deve ser booleano.
- **RF-1c** — A listagem de posts aceita filtro `?status=` (inclui `awaiting_upload`).
- **RF-2** — `POST /scheduled-posts/upload-complete` com `media_storage_path`: confere o arquivo no S3, e para os posts `awaiting_upload` daquele path no workspace corrente: passa para `pending`, grava `upload_completed_at` e despacha `PublishPostJob` (com `delay(scheduled_at)`; horário passado executa na hora).
- **RF-3** — `upload-complete` é idempotente: chamada repetida não despacha job duplicado nem altera posts que já saíram de `awaiting_upload`.
- **RF-3b** — `upload-complete` sobre um path cujos posts não têm mais nenhum `awaiting_upload` e nenhum com `upload_completed_at` (só `failed` por expiração e/ou `cancelled`) devolve **409**: "O prazo do envio expirou. Envie o vídeo novamente." (algum `failed`) ou "O agendamento foi cancelado." (todos `cancelled`). Posts já confirmados (`pending`/`processing`/`published`) continuam devolvendo 200 idempotente com `late: false`.
- **RF-4** — Arquivo ausente no S3 em `upload-complete`: 422 com erro de domínio, posts continuam `awaiting_upload`.
- **RF-4b (segurança)** — O `path` (mídia e thumbnail) é recusado se contiver `..`, `//`, `\\` ou caracteres de controle (todos os fluxos, VULN-UP-02). `upload-complete` exige objeto com tamanho > 0 bytes no S3 (VULN-UP-04) e confere a mídia (vídeo e thumbnail) de **cada** post do lote (VULN-UP-05).
- **RF-5** — Quando `upload_completed_at > scheduled_at`: a resposta de `upload-complete` traz `late: true` e `message` ("O envio do vídeo terminou às HH:MM, depois do horário agendado (HH:MM). Estamos publicando agora."), e o autor recebe a notificação `post_upload_late` (respeita preferências).
- **RF-6** — Posts `awaiting_upload` há mais de `scheduling.upload_expiration_hours` viram `failed` com o texto fixo "O envio do vídeo não foi concluído. Envie o vídeo novamente." e disparam a notificação de post falhado existente. Rotina agendada, mesmo molde de `posts:close-stuck`.
- **RF-7** — `cancel` passa a aceitar `awaiting_upload` além de `pending`; a mídia é apagada com `deleteIfUnused`.
- **RF-8** — Mudança de status de `awaiting_upload` para `pending`/`failed`/`cancelled` emite o evento `ScheduledPostUpdated` no canal privado `workspaces.{uuid}`.
- **RF-9** — Antecedência mínima vem de `config('scheduling.min_lead_minutes')` (env `SCHEDULE_MIN_LEAD_MINUTES`, padrão 5), usada em Store e Retry; a mensagem de erro mostra o valor configurado.
- **RF-10** — Isolamento: `upload-complete` só enxerga posts do workspace corrente (escopo de tenant); outro workspace recebe 404 e nada muda. Exige permissão `posts.create`.
- **RF-11b (front)** — Trocar de workspace com envio ativo é bloqueado com a mensagem "Há vídeos sendo enviados. Aguarde terminar para trocar de workspace.".
- **RF-11 (front)** — Fila de envios no navegador (concorrência 2), painel fixo no canto inferior direito com um card por vídeo (nome, contas, horário, barra e %), aviso do navegador ao fechar a aba com envio ativo, chip "Enviando vídeo" nos cards de post `awaiting_upload`, e popup + sino para os desfechos.

## Contrato de API

- `POST /scheduled-posts` (+ `media_pending`): 201 com os posts em `status: awaiting_upload`.
- `POST /scheduled-posts/upload-complete` `{ media_storage_path }`: 200 `{ message, late, data: [posts] }`; 404 (outro workspace/sem posts), 403 (sem permissão), 409 (prazo expirado ou agendamento cancelado), 422 (arquivo ausente/vazio ou path inválido).
- Status novo `awaiting_upload` ("Aguardando envio do vídeo") nos Resources e filtros de listagem.
- Evento websocket `ScheduledPostUpdated` com `{ uuid, status }`.
- Notificação nova `post_upload_late` (contrato entregue pelo agente `notifications`).

## Critérios de aceite

- [ ] Clique 16:45 para 16:50 → 201 `awaiting_upload`, nenhum job despachado.
- [ ] `upload-complete` 16:47 → `pending`, job com delay até 16:50; `late: false`.
- [ ] `upload-complete` 16:53 → job imediato, `late: true`, mensagem e notificação.
- [ ] Clique 16:46 para 16:50 (antecedência 5) → 422.
- [ ] `upload-complete` sem arquivo no S3 → 422; posts continuam `awaiting_upload`.
- [ ] `upload-complete` repetido → sem job duplicado.
- [ ] Outro workspace → 404 e banco inalterado; sem permissão → 403.
- [ ] Expiração → `failed` com texto fixo e notificação; `cancel` de `awaiting_upload` funciona.
- [ ] Config 15 altera regra e mensagem em Store e Retry.
- [ ] Fluxo sem `media_pending` idêntico ao atual.

## Fora de escopo

- Retomar upload após recarregar a página (o `File` não sobrevive ao reload; coberto por RF-6).
- Upload multipart/resumível.
- Alterar `.env.example` (valor de produção é decisão de deploy).

## Questões em aberto

Nenhuma. Decisões:
1. `upload_expiration_hours` = 24h (igual à validade da presigned) — aprovado.
2. Cota do plano: `consumeQuota` segue no-op; tratar `awaiting_upload` na cota fica para quando o billing de cota for ativado — adiado pelo dono do produto.
