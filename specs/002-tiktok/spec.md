# Spec 002 — Integração com o TikTok (conexão de conta e publicação de vídeo)

**Status:** aprovada (2026-10-05) — questões em aberto decididas na seção "Decisões" · **Branch:** `feat/tiktok` · **Última revisão:** 2026-10-05

> Área crítica (OAuth social + esteira de publicação). Por isso a seção **[A] Comportamento atual** é uma spec reversa do que está no código hoje (commit `0775125 wip: tiktok creating` + `CheckTikTokPostStatusJob`). As seções seguintes descrevem o comportamento **desejado** e a limpeza do código TikTok.

---

## Contexto

O Pandypost publica vídeos agendados em YouTube, Instagram e TikTok. YouTube e Instagram já operam com APIs reais; o TikTok saiu do estado de *stub* (conta e post simulados) para uma integração real com a **TikTok Content Posting API v2 (Direct Post)** num commit WIP, sem spec e sem revisão.

O objetivo desta spec é:

1. Registrar o comportamento atual (spec reversa).
2. Definir o comportamento esperado da integração, corrigindo divergências em relação ao contrato da API do TikTok.
3. Deixar o código TikTok alinhado à arquitetura do projeto (erros de domínio, Strategy/Factory, testes em Pest), sem mudar o contrato da API do Pandypost.

---

## [A] Comportamento atual (spec reversa)

### A.1 Conexão de conta (OAuth)

| Etapa | Onde | O que acontece hoje |
|---|---|---|
| Iniciar | `GET /api/social-accounts/tiktok/auth` → `TikTokOAuthProvider::getRedirectUrl` | Exige workspace corrente (senão `SocialAccountException::oauthInitializationFailed`, 500). Retorna `{url}` para `https://www.tiktok.com/v2/auth/authorize/` com `client_key`, `scope=user.info.basic,video.publish,video.upload`, `response_type=code`, `redirect_uri` e `state = encrypt(workspace.uuid)`. |
| Callback | `GET /api/social-accounts/tiktok/callback` (público) → `SocialAccountController::callback` | Decripta o `state`, acha o workspace e chama `TikTokOAuthProvider::syncAccount`. Redireciona o popup para `{FRONTEND_URL}/social-accounts/callback?success=1` ou `?error=<mensagem>`. |
| Trocar código | `syncAccount` | Sem `code` → `authFailed('TikTok')`. `POST /v2/oauth/token/` (`grant_type=authorization_code`). Sem `access_token` ou `open_id` → `authFailed('TikTok')`. |
| Perfil | `syncAccount` | `GET /v2/user/info/?fields=open_id,union_id,avatar_url,display_name`. Falha aqui **não** é tratada: usa `nickname = 'TikTok User'` e `avatar = null`. |
| Persistir | `syncAccount` | `SocialAccount::updateOrCreate` por (`workspace_id`, `platform=tiktok`, `platform_id=open_id`) com tokens, `expires_at = now + expires_in` (padrão 86400 s), nickname e avatar. Quando o contexto é um `Workspace`, `user_id` = primeiro acesso do workspace. |
| Avatar | migration `2026_09_07_..._change_avatar_column_type` | `avatar` virou `text` (URLs de avatar do TikTok passam de 255 chars). |

### A.2 Renovação de token

`SocialAccount::getValidToken()` renova quando falta ≤ 5 min para expirar. Para TikTok, `refreshTikTokToken()` faz `POST /v2/oauth/token/` (`grant_type=refresh_token`) e grava `access_token`, `refresh_token` (rotacionado, se vier) e `expires_at`. Sem `refresh_token` ou com resposta inválida → **`\Exception` genérica**.

### A.3 Desconexão

`DELETE /api/social-accounts/{uuid}` cancela posts `pending` da conta e apaga o registro. `revokeToken()` **não** revoga no TikTok (só YouTube é revogado).

### A.4 Agendamento

`POST /api/scheduled-posts` aceita, além dos campos comuns, os campos opcionais:

| Campo | Validação |
|---|---|
| `tiktok_privacy_level` | `PUBLIC_TO_EVERYONE`, `MUTUAL_FOLLOW_FRIENDS`, `SELF_ONLY`, `FOLLOWER_OF_CREATOR` |
| `tiktok_disable_comment` / `tiktok_disable_duet` / `tiktok_disable_stitch` | boolean |
| `tiktok_brand_content_toggle` | boolean |

`TikTokPayloadBuilder` move esses campos para `scheduled_posts.payload`. A lista de privacidades está **hard-coded** no FormRequest (o YouTube usa enum `YouTubePrivacyStatus`).

### A.5 Publicação (`PublishPostJob` → `TikTokService::upload`, fila `tiktok`)

1. Post → `processing`. Obtém token válido e o tamanho do arquivo no S3.
2. Fatiamento: ≤ 64 MB → 1 pedaço; acima disso, pedaços de 10 MB com `total_chunk_count = ceil(tamanho / 10 MB)`.
3. `POST /v2/post/publish/video/init/` com `post_info` (`title` = caption, ou título, ou vazio; privacidade padrão `PUBLIC_TO_EVERYONE`; `disable_*` padrão `false`; `brand_content_toggle` só se enviado) e `source_info` `FILE_UPLOAD`.
4. Erro `unaudited_client_can_only_post_to_private_accounts` → post `failed` com mensagem pt-BR explicando app não auditado e **`\Exception` genérica**. Outro erro (ou sem `publish_id`/`upload_url`) → `failed` com a mensagem do TikTok e `\Exception` genérica.
5. Envia os pedaços via `PUT upload_url` (`Content-Type: video/mp4` fixo, `Content-Range`). Falha HTTP → `failed` e `\Exception` genérica.
6. Grava `container_id = publish_id` e `container_created_at`, e despacha `CheckTikTokPostStatusJob`.

Qualquer exceção sobe para o `PublishPostJob`, que tenta de novo (até 3×, **inclusive para erros definitivos** como app não auditado) e, no `failed()`, marca `failed` e apaga mídia + thumbnail com `deleteMany`.

### A.6 Acompanhamento do status (`CheckTikTokPostStatusJob`, fila `tiktok`)

- `POST /v2/post/publish/status/fetch/` com o `publish_id`. Até 15 tentativas, `release()` com atraso 2, 4, 8, 16, 30, 30… s (≈ 5–6 min no total).
- `PUBLISH_COMPLETE` → `published`, `platform_post_id = data.publicity_id ?? publish_id`, `published_at`; apaga a mídia com `deleteIfUnused` (a thumbnail não).
- `FAILED` → `failed` com `"Erro no TikTok: {fail_reason}"`, apaga a mídia e falha o job.
- Qualquer outro status (inclusive resposta de erro/sem `data`) → tenta de novo.
- Tentativas esgotadas → o job falha **sem `failed()`**: o post fica `processing` para sempre e a mídia nunca é apagada.

### A.7 Link do post

`ScheduledPost::getPlatformPostUrl()` → `https://www.tiktok.com/video/{platform_post_id}` (exposto como `platform_post_url` no `ScheduledPostResource`).

### A.8 Testes existentes

`tests/Feature/TikTokIntegrationTest.php` — 6 testes, **passando** em 2026-10-05. Estilo PHPUnit (classe), fora de `tests/Feature/<Dominio>/` e sem `describe()/it()`. Cobre: URL de redirect, sync de conta, refresh de token, init + dispatch do job, `PUBLISH_COMPLETE`, erro de app não auditado.

### A.9 Divergências encontradas (contra a documentação oficial do TikTok)

| # | Divergência | Efeito |
|---|---|---|
| D1 | O status fetch retorna `publicaly_available_post_id` (lista, grafia do TikTok); o código lê `publicity_id`, que não existe. | `platform_post_id` sempre recebe o `publish_id` e o `platform_post_url` gerado é inválido. |
| D2 | O TikTok exige `total_chunk_count = floor(tamanho / chunk_size)`, com o último pedaço absorvendo o resto (até 128 MB). O código usa `ceil`. | Vídeos > 64 MB cujo tamanho não é múltiplo de 10 MB são rejeitados no init. |
| D3 | `Content-Type` fixo em `video/mp4`; o TikTok aceita MP4, MOV e WebM. | Upload de `.mov`/`.webm` envia o MIME errado. |
| D4 | Tentativas esgotadas no job de status deixam o post preso em `processing` (A.6). | Post nunca termina; mídia órfã no S3. |
| D5 | Uso de `\Exception` genérica em `TikTokService` e `refreshTikTokToken` (CLAUDE.md pede enum + exception de domínio). | Fora do padrão; mensagens/códigos não padronizados. |
| D6 | Os pedaços são lidos com `fread($stream, N)`; em stream de rede (S3), `fread` devolve no máximo um pacote (~8 KB), não `N` bytes. | Pedaços enviados com tamanho errado → upload corrompido/recusado em produção (nos testes passa porque o stream é `php://temp`). |
| D7 | Pede o escopo `video.upload` (rascunho na inbox), que a integração não usa; Direct Post só exige `video.publish`. O usuário pode desmarcar `video.publish` na tela do TikTok e a conta conecta mesmo assim. | Escopo a mais na auditoria; conta "conectada" que não consegue publicar. |

---

## Escopo

**Dentro:**

- Corrigir D1–D7 no código do TikTok.
- Limpeza do código TikTok seguindo a arquitetura: erros de domínio, enum para a privacidade, constantes de endpoint num lugar só, métodos pequenos em `TikTokService` (como em `InstagramService`/`YouTubeService`), estilo de `config/services.php`.
- Migrar os testes do TikTok para Pest em `tests/Feature/TikTok/`, com cobertura dos critérios de aceite.

**Arquivos no escopo:** `TikTokService`, `CheckTikTokPostStatusJob`, `TikTokOAuthProvider`, `TikTokPayloadBuilder`, `SocialAccount::refreshTikTokToken` (e o `match` de `revokeToken`, se Q2 = sim), campos `tiktok_*` do `StoreScheduledPostRequest`, braço TikTok de `ScheduledPost::getPlatformPostUrl`, bloco `tiktok` de `config/services.php`, testes do TikTok.

---

## Requisitos

- **RF-1 — Conectar conta.** O usuário com `social_accounts.connect` obtém a URL de autorização do TikTok (escopos `user.info.basic` e `video.publish`); ao autorizar, a conta é criada/atualizada no workspace corrente, identificada pelo `open_id`, com nome e avatar do perfil. Se o usuário não conceder `video.publish`, a conexão é recusada com mensagem pt-BR (D7).
- **RF-2 — Renovar token.** Tokens vencidos são renovados de forma transparente antes de qualquer chamada. Sem refresh token, ou com refresh recusado, o erro é de domínio, em pt-BR, orientando a reconectar a conta.
- **RF-3 — Opções do post.** O agendamento aceita privacidade, desativar comentários/dueto/stitch e conteúdo de marca, com os mesmos nomes e valores de hoje.
- **RF-4 — Enviar o vídeo.** Vídeos menores que 10 MB vão inteiros; os demais vão em pedaços de 10 MB, com o último absorvendo o resto (regra do TikTok, D2), lidos do S3 em stream com o tamanho exato (D6) e com o MIME correspondente à extensão do arquivo (D3).
- **RF-5 — Falha no envio.** Qualquer recusa do TikTok no init ou no upload lança erro de domínio (D5) com mensagem pt-BR; os códigos de erro documentados pelo TikTok (app não auditado, privacidade indisponível, limite de posts, token inválido, escopo não autorizado etc.) têm mensagem própria. Ao fim das tentativas do `PublishPostJob`, o post fica `failed` com essa mensagem.
- **RF-6 — Acompanhar a publicação.** Após o envio, o sistema consulta o status até `PUBLISH_COMPLETE` ou `FAILED`:
  - `PUBLISH_COMPLETE` → `published`, `published_at` e `platform_post_id` com o id público do vídeo (D1) quando o TikTok o fornecer.
  - `FAILED` → `failed` com o motivo do TikTok.
  - Sem resposta conclusiva em até 1 h → `failed` com mensagem pt-BR (D4).
- **RF-7 — Limpeza de mídia.** Ao terminar (sucesso, falha ou tentativas esgotadas), a mídia é apagada do S3 se nenhum outro post `pending`/`processing` a usar.
- **RF-8 — Link do post.** `platform_post_url` aponta para o vídeo no TikTok quando há id público; caso contrário é `null`.
- **RF-9 — Desconectar.** Ao desconectar, o token é revogado no TikTok (como já é feito no YouTube).

**Não funcionais:**

- **RNF-1** Contrato da API do Pandypost inalterado (rotas, campos, respostas).
- **RNF-2** Nenhuma chamada real ao TikTok em testes (`Http::fake`).
- **RNF-3** Código em inglês, mensagens em pt-BR; Pint nos arquivos alterados.

---

## Contrato de API

**Sem rotas novas e sem mudança de payload.** Rotas envolvidas:

| Método | Rota | Permissão | Resposta |
|---|---|---|---|
| GET | `/api/social-accounts/tiktok/auth` | `social_accounts.connect` | `200 {"url": "https://www.tiktok.com/v2/auth/authorize/?..."}` |
| GET | `/api/social-accounts/tiktok/callback` | pública (`state` criptografado) | `302` → `{FRONTEND_URL}/social-accounts/callback?success=1` ou `?error=...` |
| POST | `/api/scheduled-posts` | `posts.create` | `201`, aceita os campos `tiktok_*` de A.4 |
| GET | `/api/scheduled-posts` | `posts.view` | `platform_post_url` passa a ser válido para TikTok (RF-8) |
| DELETE | `/api/social-accounts/{uuid}` | `social_accounts.disconnect` | `200`; revoga o token no TikTok |

---

## Critérios de aceite

- **CA-1** A URL de auth contém `client_key`, os escopos `user.info.basic,video.publish`, `redirect_uri` e um `state` que decripta para o uuid do workspace corrente; sem workspace → 500 `oauth_initialization_failed`.
- **CA-2** Callback com `code` válido cria a conta TikTok no workspace do `state`; repetir o fluxo com o mesmo `open_id` atualiza a conta em vez de duplicar.
- **CA-3** Callback sem `code`, com token sem `open_id`, sem o escopo `video.publish` ou com `state` inválido redireciona para `?error=` e não cria conta.
- **CA-4** Token vencido é renovado e o novo `refresh_token` é salvo; sem refresh token ou refresh recusado lança exception de domínio (não `\Exception`).
- **CA-5** Vídeo de 23 bytes → 1 pedaço. Vídeo de 65 MB → `chunk_size` 10 MB, `total_chunk_count` 6, último pedaço com 15 MB e `Content-Range` corretos; cada pedaço tem exatamente o tamanho declarado mesmo quando o stream entrega poucos bytes por leitura.
- **CA-6** `.mov` é enviado com `video/quicktime` e `.webm` com `video/webm`.
- **CA-7** Erro no init (incluindo app não auditado) ou HTTP ≠ 2xx no upload → `ScheduledPostException` com mensagem pt-BR; o job de status não é despachado; ao esgotar as tentativas do `PublishPostJob`, o post fica `failed` com a mensagem.
- **CA-8** `PUBLISH_COMPLETE` com `publicaly_available_post_id: [123]` → `published`, `platform_post_id = "123"`, mídia apagada se não estiver em uso.
- **CA-9** `FAILED` → `failed` com o `fail_reason`; mídia apagada se não estiver em uso.
- **CA-10** Prazo de acompanhamento esgotado (ou erros repetidos na consulta) → `failed` com mensagem pt-BR; mídia apagada se não estiver em uso.
- **CA-16** Desconectar uma conta TikTok chama o endpoint de revogação do TikTok.
- **CA-11** `platform_post_url` válido quando há id público; `null` quando não há.
- **CA-12** Os campos `tiktok_*` inválidos retornam 422; válidos chegam ao `payload` do post.
- **CA-13** Isolamento: o callback com `state` do workspace A nunca cria/atualiza conta no workspace B; usuário do workspace B não vê nem usa a conta TikTok do A no agendamento.
- **CA-14** Usuário sem `social_accounts.connect` recebe 403 em `/social-accounts/tiktok/auth`.
- **CA-15** Suíte completa verde; testes do TikTok em Pest (`tests/Feature/TikTok/`, `describe()/it()` em pt-BR), sem chamadas reais.

---

## Fora de escopo

- Consulta de `creator_info` antes de postar (lista de privacidades permitidas por criador, limite de duração, bloqueio de comentário/dueto/stitch por criador) — exigida pelas diretrizes de auditoria do TikTok; vira spec própria se Q5 = sim.
- Upload por `PULL_FROM_URL`, postagem como rascunho (inbox), fotos/carrossel, analytics do TikTok.
- Mudanças na esteira compartilhada (`PublishPostJob`): retentativa de erros definitivos e `deleteMany` sem checar uso no `failed()` — ficam como **apontamentos** abaixo.

### Apontamentos (fora do escopo, não serão corrigidos aqui)

- `PublishPostJob` tenta de novo 3× erros definitivos de qualquer plataforma (ex.: app não auditado), repetindo o init no TikTok.
- `PublishPostJob::failed()` apaga mídia com `deleteMany`, sem checar se outro post usa o mesmo arquivo (as plataformas usam `deleteIfUnused`).
- `CheckInstagramContainerJob` tem o mesmo problema de D4 (sem `failed()` ao esgotar tentativas).
- `refreshYouTubeToken`/`refreshInstagramToken` e `PublishPostJob` também usam `\Exception` genérica.
- `YouTubeService::processResumableStream` lê o S3 com `fread($handle, 1 MB)`, que tem o mesmo problema de D6 (o upload resumable do Google exige pedaços múltiplos de 256 KB).
- `PublishPostJob::failed()` corta a mensagem com `substr` (bytes): uma mensagem pt-BR com acento na posição 255 vira UTF-8 inválido e o PostgreSQL recusa o update. As mensagens do TikTok foram mantidas abaixo de 255 bytes.
- `ScheduledPost` não tem `workspace_id` no `$fillable` nem `BelongsToWorkspace`; os testes passam `workspace_id` e ele é ignorado.

---

## Decisões (questões em aberto resolvidas em 2026-10-05)

Delegadas pelo usuário ("faça o que for melhor para funcionar de forma profissional").

- **Q1 — Link do post:** `https://www.tiktok.com/@/video/{id}`. O TikTok resolve o vídeo pelo id; gravar o `@usuario` exigiria o escopo `user.info.profile`, que precisa ser habilitado e auditado no portal e obrigaria a reconectar as contas. **Validar no teste manual**; se o link não abrir, a alternativa é pedir `user.info.profile`.
- **Q2 — Revogar ao desconectar:** sim, via `POST /v2/oauth/revoke/` (RF-9).
- **Q3 — Janela de acompanhamento:** 1 h (`retryUntil`), consultando a cada 5, 10, 20, 30 e depois 60 s; até 3 erros de consulta. `PUBLISH_COMPLETE` sem id público (post privado ou ainda em moderação) → `published` com `platform_post_id = null`.
- **Q4 — Erros de domínio:** casos novos nos enums existentes (`ScheduledPostError::PUBLISH_FAILED`, `SocialAccountError::TOKEN_REFRESH_FAILED` e `SocialAccountError::MISSING_PERMISSIONS`), sem enum/exception dedicada ao TikTok.
- **Q5 — `creator_info`:** recomendado antes de submeter o app à auditoria; fica para a spec 003 (precisa de endpoint novo e de UI no front).
- **Q6 — `routes/web.php`:** a rota `/` → `view('welcome')` é removida (a view não existe, então a rota respondia 500).

## Questões em aberto

Nenhuma no momento.

<details><summary>Questões originais (histórico)</summary>


- **Q1 — Link do post (RF-8).** O formato canônico é `https://www.tiktok.com/@{username}/video/{id}`. Gravar o `username` na conexão (pede o campo `username` no `user/info`, escopo `user.info.profile`) ou manter `/video/{id}` sem usuário? Com escopo novo, contas já conectadas precisariam reconectar.
- **Q2 — Revogar no TikTok ao desconectar?** O TikTok tem `POST /v2/oauth/revoke/`. Hoje só o YouTube é revogado.
- **Q3 — Janela de acompanhamento (RF-6/D4).** Hoje ≈ 5–6 min. O TikTok diz que a moderação costuma levar ~1 min, mas pode levar horas. Manter a janela atual e marcar `failed` ao esgotar, ou aumentar (ex.: até 1 h)? Também: se vier `PUBLISH_COMPLETE` **sem** id público (post privado, ou ainda em moderação), marcamos `published` com `platform_post_id = null`?
- **Q4 — Erro de domínio.** Criar `TikTokError` + `TikTokException` (dedicados) ou acrescentar casos em `ScheduledPostError`/`SocialAccountError` (ex.: `PUBLISH_INIT_FAILED`, `MEDIA_UPLOAD_FAILED`, `TOKEN_REFRESH_FAILED`)? Recomendo a segunda: reaproveita os enums existentes e não cria abstração nova.
- **Q5 — `creator_info`.** O app vai passar pela auditoria do TikTok em breve? Se sim, a consulta de `creator_info` (e o front mostrando as opções do criador) deve virar a próxima spec.
- **Q6 — `routes/web.php`.** A rota `/` → `welcome` entrou no commit WIP do TikTok, mas a view não existe. Foi para a verificação de domínio/URL do app no TikTok? Se não, removo.

</details>
