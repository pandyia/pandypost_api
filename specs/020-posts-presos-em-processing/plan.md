# Plano 020 — Posts presos em `processing`

> Spec aprovada pelo dono em 2026-11-06 (questões em aberto confirmadas com as sugestões da spec: comando a cada 5 min, mensagens sugeridas, log só com id/código, promoção tardia só enquanto `failed`).

## Decisões
- **D1** Um `PostReconciliationService` concentra a regra (consultar a rede → decidir). Comando e `retry()` só chamam o service. *Descartado:* um job por rede (mais peças, e o comando já roda a cada 5 min).
- **D2** Conferência por rede, sem identificador novo no banco: TikTok = `container_id` (publish_id) via `TikTokApi::fetchStatus`; Instagram = `container_id` via `InstagramApi::containerStatus`; YouTube = `platform_post_id` (passa a ser gravado logo após o upload).
- **D3** Resultado: rede diz publicado → `published`; diz falha (ou Instagram `FINISHED` sem publicar, `EXPIRED`) → `failed` com a mensagem da rede; ainda processando ou erro de consulta → espera, até 2× o limite → `failed` (`STUCK_TIMEOUT`); sem identificador → `failed` (`UNVERIFIABLE`).
- **D4** Troca de estado com `lockForUpdate` + conferência do status dentro da transação e `update()` do model (mantém observers: `failed_at`, notificações). Rodar duas vezes não repete.
- **D5** Promoção tardia: posts `failed` nas últimas 6 h com mensagem do comando e identificador são reconferidos; se a rede confirmou, viram `published`. O `/retry` também confere antes de reenviar e responde 409 (`ALREADY_PUBLISHED`) se o vídeo já está na rede.
- **D6** Antiduplicidade no envio: YouTube grava o `videoId` antes da thumbnail e, se já existe, não reenvia; TikTok grava o `publish_id` logo após o `init` e, num novo `upload()` com `publish_id`, consulta o status antes de iniciar outro Direct Post.
- **D7** Mídia: sem mudança. `published` usa `deleteIfUnused` (outras redes que usam o mesmo arquivo o mantêm); `failed` mantém o arquivo.

## Arquivos
- `app/Services/PostReconciliationService.php` (novo), `app/Console/Commands/CloseStuckPostsCommand.php` (`posts:close-stuck`), `routes/console.php` (a cada 5 min, `withoutOverlapping`).
- `ScheduledPostError` + `ScheduledPostException`: `STUCK_TIMEOUT`, `UNVERIFIABLE`, `ALREADY_PUBLISHED`.
- `ScheduledPostService::retry`, `YouTubeService::upload`, `TikTokService::upload`.

## Testes (`tests/Feature/ScheduledPosts/StuckPostsTest.php`)
Limites por rede (29/31 TikTok, 45/61 Instagram), outros status intactos, rede confirma → published (sem notificação de falha), rede falha → failed + 1 notificação, sem id → UNVERIFIABLE, idempotência, promoção tardia, `retry` bloqueado se publicado, YouTube/TikTok sem reenvio duplicado, mídia compartilhada preservada, mensagens sem dado de terceiros. APIs com `Http::fake`.

## Riscos
Consulta de status depende da disponibilidade da rede (por isso espera até 2× o limite). Instagram `PUBLISHED` fecha sem id da mídia (comportamento já existente).
