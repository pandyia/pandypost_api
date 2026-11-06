# 011 — Publicação no TikTok (Direct Post)

## Contexto
Hoje é possível conectar uma conta TikTok (OAuth) e ver analytics (spec 003), mas **publicar não funciona**: `TikTokService::upload()` é um stub que marca o post como `published` com `platform_post_id` fake (`tiktok_stub_...`), não chama o TikTok e apaga o vídeo do S3. O usuário vê "publicado" e nada foi ao ar. Esta spec implementa a publicação real via **Content Posting API – Direct Post**.

## Spec reversa (esteira de publicação + OAuth social — área crítica)
- `PublishPostJob` (fila `tiktok`, `tries=3`, `timeout=180`) valida assinatura e a conta e chama `SocialMediaFactory::make(TIKTOK)->upload($account, $post)`. Falha final → `failed()` marca `failed`, apaga mídia/thumbnail do S3 e notifica (`notifyPostFailed`).
- `TikTokService::upload()` é stub (descrito acima). Não gera `publish_id`.
- `CheckTikTokPostStatusJob` (fila `tiktok`, `tries=15`, backoff 2–30s) consulta `/v2/post/publish/status/fetch/` e trata `PUBLISH_COMPLETE` / `FAILED` / em andamento. **Nenhum código o dispara.** No `FAILED` ele chama `$this->fail()`, mas a mensagem já foi gravada no post.
- `TikTokOAuthProvider::SCOPES` = `user.info.basic,user.info.profile,user.info.stats,video.list` — **sem escopo de publicação**. `state` = `encrypt(workspace uuid)` (sem nonce/expiração: achado da spec 002, fora daqui).
- `SocialAccount::getValidToken()` renova o token TikTok com `refresh_token`; falha lança `\Exception` genérica (débito, fora de escopo).
- `TikTokPayloadBuilder` é o `GenericPayloadBuilder`: o `payload` do post é livre, sem regras TikTok.
- `app/Http/Controllers/TikTokAuthController.php` é código de teste legado (state fixo, `env()`, `storage/app/video.mp4`), **não roteado**.

## Escopo
Publicar vídeo agendado no TikTok via Direct Post (`FILE_UPLOAD` em chunks), acompanhar até o fim, e expor ao front o necessário para o usuário configurar o post (privacidade, interações, divulgação comercial). Inclui o pedido do escopo `video.publish` no OAuth.

## Requisitos
- RF-1: o OAuth do TikTok passa a pedir também `video.publish` (e `video.upload`, necessário para o fluxo FILE_UPLOAD). Contas conectadas antes **não** têm o escopo e precisam reconectar.
- RF-2: conta sem o escopo de publicação (guardar os escopos concedidos na conexão) não publica: o post falha com mensagem clara "reconecte a conta TikTok". Não chama a API.
- RF-3: `GET /api/social-accounts/{socialAccount}/tiktok/creator-info` devolve, via `creator_info/query`, `creator_nickname`, `privacy_level_options`, `comment_disabled`, `duet_disabled`, `stitch_disabled`, `max_video_post_duration_sec`. Exigência do TikTok: o front só pode oferecer as privacidades que a conta permite.
- RF-4: `payload` do post TikTok é validado (via `TikTokPayloadBuilder`/FormRequest):
  - `privacy_level` (obrigatório, um dos retornados pelo creator-info; **sem valor padrão** — o usuário escolhe);
  - `disable_comment`, `disable_duet`, `disable_stitch` (booleanos);
  - `brand_content_toggle` / `brand_organic_toggle` (divulgação comercial; conteúdo de marca não pode ser `SELF_ONLY`);
  - título/legenda usa `caption` (ou `title`, se não houver legenda) do post (limite do TikTok: 2200 caracteres).
- RF-5: `TikTokService::upload()`:
  1. `post->status = processing`; obtém token válido;
  2. consulta `creator_info/query` e valida `privacy_level` contra as opções devolvidas (não há validação local de duração: o TikTok recusa no `init`/status e o erro vira mensagem pt-BR, ver Decisão TDD 2);
  3. `POST /v2/post/publish/video/init/` com `source_info` `FILE_UPLOAD` (`video_size`, `chunk_size`, `total_chunk_count`) e `post_info`;
  4. envia o arquivo do S3 por `PUT` na `upload_url` em chunks (5 MB–64 MB; último chunk até 128 MB) com `Content-Range` e `Content-Type`;
  5. grava `publish_id` no post e dispara `CheckTikTokPostStatusJob`.
- RF-6: o vídeo **só** é apagado do S3 quando o post é **publicado** (`PUBLISH_COMPLETE`). Em falha a mídia fica no S3 para reenvio (spec 012; limpeza em `posts:purge-failed-media`, ver Decisão TDD 3). O stub deixa de apagar na hora.
- RF-7: `CheckTikTokPostStatusJob` passa a ser o único a marcar `published` (com `platform_post_id`/`publicity_id` e `published_at`) ou `failed`. Esgotadas as tentativas sem estado final, o post vira `failed` com mensagem de tempo esgotado (hoje ficaria `processing` para sempre).
- RF-8: erros da API do TikTok viram `error_message` em pt-BR com mapeamento dos códigos conhecidos (`spam_risk_too_many_posts`, `reached_active_user_cap`, `unaudited_client_can_only_post_to_private_accounts`, `scope_not_authorized`, `access_token_invalid`, `url_ownership_unverified`, `file_format_check_failed`...). Nunca expor `body` cru nem token na resposta ou no `error_message`.
- RF-9: falha definitiva dispara a notificação de falha existente (`notifyPostFailed`) — sem tipo novo.
- RF-10: remover o stub e o `TikTokAuthController` legado.

## Contrato de API
- `GET /api/social-accounts/{socialAccount}/tiktok/creator-info` — permissão do grupo de contas sociais/posts (definir no plan); 404 para conta de outro workspace; 409 se a conta não tiver o escopo de publicação ou o token for inválido. Resposta via `JsonResource`.
- Criação/edição de post TikTok existente passa a validar o `payload` (RF-4); erro de validação 422 padrão.
- Nenhuma rota nova para publicar: continua pelo agendamento existente.

## Critérios de aceite
Pest com `Http::fake` (nunca a API real): sucesso ponta a ponta (init → chunks → status `PUBLISH_COMPLETE`), vídeo de 1 chunk e multi-chunk, `FAILED` com motivo, tempo esgotado, conta sem escopo, token expirado com renovação, `privacy_level` inválido, conteúdo de marca + `SELF_ONLY`, vídeo mais longo que o permitido, S3 só limpo no estado final, creator-info (200/403/404 isolamento/409), nenhum token ou `body` bruto nas respostas.

## Fora de escopo
- Posts de foto/carrossel; `PULL_FROM_URL` (exige domínio verificado); agendamento nativo do TikTok.
- `state` do OAuth com nonce/expiração (spec 002); troca das `\Exception` genéricas do refresh de token.
- Auditoria do app pelo TikTok (processo externo, ver abaixo).
- Tela do front (privacidade, interações, aviso de divulgação comercial).

## Restrições externas (não são código)
Checklist de release (plan §7). Nada aqui muda código ou config; é preciso fazer antes de publicar para clientes.
- [ ] **Painel TikTok for Developers:** produto Content Posting API com **Direct Post** ligado; scopes `user.info.basic`, `user.info.profile`, `user.info.stats`, `video.list`, `video.upload`, `video.publish`; redirect URI de **produção** `{APP_URL}/api/social-accounts/tiktok/callback`; termos de uso e política de privacidade públicos.
- [ ] **Auditoria:** submeter o app com o fluxo gravado cumprindo as UX Guidelines (privacidade escolhida pelo usuário, interações, divulgação comercial, nome da conta de destino). Enquanto não auditado, o Direct Post publica só como `SELF_ONLY` e em contas privadas/sandbox (erro `unaudited_client_can_only_post_to_private_accounts`).
- [ ] **Variáveis de produção:** `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`, `TIKTOK_REDIRECT_URI` no gerenciador de secrets de produção (não em `.env.example`). `docker-compose.prod.yml`, `docker/` e `.github/` não são alterados por esta spec.
- [ ] **Horizon:** worker da fila `tiktok` ativo em produção; `tries`/`backoff` do `CheckTikTokPostStatusJob` cobrem ~5 min de processamento.
- [ ] **Rate limit do TikTok:** o limite de posts por conta/24h (`spam_risk_too_many_posts`) é erro mapeado, sem retry cego.
- [ ] **Release notes:** contas TikTok conectadas antes precisam reconectar (escopo `video.publish`); o front usa `can_publish` da conta para avisar.
- [ ] **Logs:** nunca com token ou `body` cru do TikTok (coberto por `tests/Feature/Security/TikTokSecurityTest.php`).

## Decisões (2026-11-05)
> O Gabriel delegou as respostas ("decida a maneira mais profissional"). Critério: o que o TikTok exige na auditoria do app, porque só o app auditado publica em público (é o que sustenta o produto).

1. **Permissão do `creator-info`:** `social_accounts.view` (é dado da conta, não de post).
2. **Conta sem escopo:** falha com "reconecte" (RF-2) **e** a `SocialAccountResource` expõe `can_publish` (bool) para o front avisar ao agendar/listar. Exige guardar os escopos concedidos (`scopes`).
3. **Validação de vídeo:** só a duração máxima do `creator-info`, checada na hora de publicar (o limite muda por conta). Vertical/duração mínima: não validar; o erro do TikTok vira mensagem em pt-BR (RF-8).
4. **`privacy_level` não mais permitido na hora de publicar:** o post **falha** com mensagem clara. Nunca rebaixar em silêncio (mudaria a visibilidade que o usuário escolheu).
5. **Divulgação comercial:** entra agora (RF-4). Faz parte das UX Guidelines que o TikTok confere na auditoria; o backend só valida, a tela é do front.

## Requisitos adicionados
- RF-11: guardar `username` (`user.info.profile`) na conta; `ScheduledPost::getPlatformPostUrl()` devolve `https://www.tiktok.com/@{username}/video/{id}` só quando há `username` e o ID público do vídeo (`publicaly_available_post_id`). Sem eles, `null`. Em sandbox o post fica privado e não há ID público.
- RF-12: guardar na conta os `scopes` concedidos (campo `scope` da resposta do token) e expor `can_publish`.

## Decisões da fase TDD (2026-11-05)
> Levantadas pelo agente `tdd`; decididas sob a delegação do Gabriel ("a maneira mais profissional").

1. **Request:** campos planos, sem prefixo (`privacy_level`, `disable_comment`, `disable_duet`, `disable_stitch`, `brand_content_toggle`, `brand_organic_toggle`), como os `youtube_*`; o `TikTokPayloadBuilder` os move para `payload`. Valor inválido → `ValidationException` com erro em `privacy_level` (422).
2. **Duração (altera RF-5.2):** **não** há validação local de duração (não existe fonte confiável do dado). O TikTok recusa no `init`/status e o erro vira mensagem pt-BR (RF-8). `->todo()` do teste é removido.
3. **Mídia em falha (altera RF-6):** o S3 só é apagado em **sucesso**. Em falha a mídia fica para reenvio (spec 012, `PostRetry`, limpeza em `posts:purge-failed-media`). Vale o comportamento já existente.
4. **Quem marca `failed`:** no `upload()`, o erro **propaga** como `TikTokException` e `PublishPostJob::failed()` é o único a gravar `failed` (um lugar só, sem marcar antes do retry). No status, o `CheckTikTokPostStatusJob` marca `failed`. Retry de erro permanente é desperdício, mas mudar o `PublishPostJob` fica como apontamento.
5. **Refresh de token (RF-13, novo):** `SocialAccountException::tokenRefreshFailed` deixa de incluir o `body` da resposta do TikTok (regra do CLAUDE.md: nunca expor resposta de terceiro). Correção mínima, só na mensagem, no caminho que esta spec já toca. A troca das `\Exception` do refresh continua fora de escopo.
6. **`publicity_id` (altera RF-7):** descartado. Só `platform_post_id` = ID público do vídeo (`publicaly_available_post_id`), ou `null`.
7. **HTTP:** 409 conta sem escopo/token inválido; 422 payload; 429 limite de posts do TikTok (`spam_risk_too_many_posts`); 502 demais erros da API do TikTok.
8. **`TikTokApi`:** métodos `creatorInfo`, `initUpload`, `uploadChunk`, `fetchStatus`; assinatura final fica com o `dev`, coberta via `Http::fake`.
9. **Creator-info de conta não-TikTok:** 404.
