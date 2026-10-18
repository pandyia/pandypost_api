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
  - título/legenda usa `title`/`description` do post (limite do TikTok: 2200 caracteres).
- RF-5: `TikTokService::upload()`:
  1. `post->status = processing`; obtém token válido;
  2. consulta `creator_info/query` e valida `privacy_level` e duração do vídeo;
  3. `POST /v2/post/publish/video/init/` com `source_info` `FILE_UPLOAD` (`video_size`, `chunk_size`, `total_chunk_count`) e `post_info`;
  4. envia o arquivo do S3 por `PUT` na `upload_url` em chunks (5 MB–64 MB; último chunk até 128 MB) com `Content-Range` e `Content-Type`;
  5. grava `publish_id` no post e dispara `CheckTikTokPostStatusJob`.
- RF-6: o vídeo **só** é apagado do S3 quando o post chega a estado final (`PUBLISH_COMPLETE` ou falha definitiva). O stub deixa de apagar na hora.
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
- **Auditoria:** enquanto o app não for auditado pelo TikTok, o Direct Post publica só como `SELF_ONLY` e em contas privadas/sandbox; erro `unaudited_client_can_only_post_to_private_accounts`. Em produção pública depende da aprovação.
- Cadastrar no painel TikTok for Developers: produto Content Posting API (Direct Post ligado), escopos `video.publish`/`video.upload`, redirect URI `{APP_URL}/api/social-accounts/tiktok/callback`.
- Preencher `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`, `TIKTOK_REDIRECT_URI` no `.env`.
- Limite de posts por conta/24h imposto pelo TikTok.

## Questões em aberto
> **Pendente de resposta do Gabriel** (perguntadas em 2026-10-18; refazer na próxima sessão). Ao responder: mover cada item para os requisitos/contrato, remover daqui e só então escrever o `plan.md`.

1. Permissão do `creator-info`: reutilizar a de contas sociais ou a de posts?
2. Contas já conectadas sem o escopo: só falhar com "reconecte" (RF-2) ou também avisar o usuário ao agendar/listar (flag na `SocialAccountResource`)?
3. Precisa de vídeo vertical/duração mínima validada no agendamento, ou só tratar o erro do TikTok?
4. Se `privacy_level` escolhido deixar de ser permitido na hora de publicar (conta virou privada), falhar ou rebaixar para `SELF_ONLY`? (sugestão: falhar)
5. Divulgação comercial entra agora (RF-4) ou numa spec depois?
