# Spec 003 — Refatoração do YouTube (publicação, conexão e analytics)

**Status:** aprovada (2026-10-06) · **Tipo:** refatoração + correções aprovadas · **Última revisão:** 2026-10-06

> **Refatoração + correções:** o objetivo é deixar o código do YouTube enxuto e confortável de ler, no padrão do TikTok (spec 002). O contrato da API não muda; os comportamentos que mudam são só as correções aprovadas na seção **Decisões**. Depende da base comum da spec 005. Área crítica (OAuth social + esteira de publicação): a seção **[A]** é a spec reversa do comportamento atual, e **não há nenhum teste do YouTube hoje** — os testes de caracterização vêm antes de qualquer mudança.

---

## Contexto

O YouTube funciona em produção: conexão via Google OAuth, publicação de vídeos com thumbnail, categorias, tags, privacidade e "feito para crianças", além de um dashboard de analytics e dos "melhores horários para postar". O código cresceu sem padrão comum:

- `YouTubeService` tem a própria lógica de renovar token, duplicando a do `SocialAccount`.
- `YouTubeAnalyticsService` tem 723 linhas, com a mesma checagem de erro de permissão repetida 3 vezes, números mágicos e blocos longos.
- `DefaultSocialiteProvider` é uma classe inteira comentada (código morto).
- Nenhum teste cobre o YouTube.

---

## [A] Comportamento atual (spec reversa)

### A.1 Conexão (Google OAuth)

| Etapa | Onde | O que acontece |
|---|---|---|
| Iniciar | `GET /api/social-accounts/google/auth` → `GoogleOAuthProvider::getRedirectUrl` | Exige workspace corrente (senão 500 `oauth_initialization_failed`). Socialite `google` *stateless*, escopos `youtube.upload`, `youtube.readonly`, `yt-analytics.readonly`, `access_type=offline`, `prompt=consent`, `state = encrypt(workspace.uuid)`. Retorna `{url}`. |
| Callback | `GET /api/social-accounts/google/callback` → `SocialAccountController::callback` | Mesmo fluxo do TikTok (decripta `state`, redireciona o popup com `?success=1` ou `?error=`). |
| Sincronizar | `GoogleOAuthProvider::syncAccount` | Socialite lê o `code` da própria request (o parâmetro `$code` é ignorado). Falha → 400 `oauth_token_exchange_failed`. `updateOrCreate` por (`workspace_id`, `platform=youtube`, `platform_id` = id do Google) com token, refresh token, `expires_at = now + expiresIn` (padrão 3600 s), nome e avatar. |

> A plataforma na URL é **`google`**, não `youtube` (`OAuthProviderFactory`). `GET /social-accounts/youtube/auth` cai no `DefaultSocialiteProvider` e quebra com erro 500 (ver Apontamentos).

### A.2 Token

- `SocialAccount::getValidToken()` renova quando falta ≤ 5 min (`refreshYouTubeToken`: `POST oauth2.googleapis.com/token`; falha → `\Exception` genérica).
- `YouTubeService::getAuthenticatedClient` **não usa** o `getValidToken()`: monta o `Google\Client` com o token cru. Como o token vai sem `created/expires_in`, o client sempre o considera vencido e **renova a cada upload** (`fetchAccessTokenWithRefreshToken`), gravando o novo token na conta.
- `YouTubeAnalyticsService` usa `getValidToken()` normalmente.
- Desconectar revoga o token em `oauth2.googleapis.com/revoke`.

### A.3 Agendamento

Campos opcionais em `POST /api/scheduled-posts`, gravados no `payload` pelo `YouTubePayloadBuilder`:

| Campo | Validação | Padrão na publicação |
|---|---|---|
| `is_short` | boolean | `false` |
| `youtube_privacy_status` | enum `public`, `private`, `unlisted` | `public` |
| `youtube_category_id` | string ≤ 10 | `22` (People & Blogs) |
| `youtube_tags` / `youtube_tags.*` | array ≤ 50 / string ≤ 50 (vazios descartados, `trim`) | sem tags |
| `youtube_made_for_kids` | boolean | `false` |

Rotas de apoio (só autenticação, sem permissão): `GET /api/youtube-categories` (tabela `youtube_categories`, ids oficiais do YouTube, via seeder) e `GET /api/youtube-privacy-statuses` (`[{value, label}]` do enum).

### A.4 Publicação (`PublishPostJob` → `YouTubeService::upload`, fila `youtube`)

1. Post → `processing`.
2. Monta o vídeo: título = `title`; descrição = `caption`; categoria, tags, privacidade e "feito para crianças" do `payload`.
3. Upload *resumable* em pedaços de 1 MB, lendo o S3 em stream (`fread`).
4. Se **não** for Short e houver `thumbnail_path`, envia a thumbnail (falha só gera log de aviso). Short nunca recebe thumbnail, porque o YouTube transforma o Short em vídeo normal.
5. Sucesso → `published`, `platform_post_id` = id do vídeo, `published_at`; apaga mídia + thumbnail com `deleteIfUnused`.
6. Qualquer exceção é logada e repassada; o `PublishPostJob` tenta 3× e, no `failed()`, marca `failed` e apaga os arquivos.

Link do post: `https://www.youtube.com/watch?v={id}`.

### A.5 Analytics (`AnalyticsController` + `YouTubeAnalyticsService`)

**`GET /api/analytics/{socialAccount}/dashboard`** (só autenticação; a conta é resolvida dentro do workspace corrente)

- Conta que não é YouTube → 400 `Analytics only supported for YouTube accounts currently`.
- Query: `date_range` (`last_7_days` padrão, `last_28_days`, `this_month`, `last_month`, `lifetime`) ou `start_date` + `end_date`; `refresh=1` força recarga, limitada a 1 vez a cada 10 min por conta+usuário (429 com mensagem em minutos).
- Período > 365 dias agrupa por mês; senão, por dia. Período anterior do mesmo tamanho para comparação.
- Cache de 6 h por conta+período (`youtube_analytics_v3_...`).
- Resposta (JSON cru, sem Resource): `overviewMetrics` (views, horas assistidas, inscritos líquidos, receita, duração média — cada um com tendência %), `timeSeriesData` (categorias + séries atual/anterior), `trafficSources` (top 5 traduzidas; vazio → 4 rótulos com zero), `topVideos` (até 50, com título, thumbnail, CTR, retenção, `is_short` ≤ 60 s, `winnerScore`, totais), `alerts` (CTR baixo, vídeo vencedor > 75, queda de views ≥ 10 %, ou "Tudo em ordem!") e `channelScore` (conteúdo + frequência de postagem).
- Erro de permissão/401/403 do Google → 401 `{message, requires_reauth: true}`; outro erro → 500 `{message, error}`.
- Receita: tenta com `estimatedRevenue`; se o canal não é monetizado, repete sem.

**`GET /api/analytics/{socialAccount}/best-times`**

- Conta não YouTube ou erro → `{best_hours: [14, 18, 20]}`.
- Senão: últimos 50 uploads, média de views por hora de publicação (fuso `America/Sao_Paulo`), top 3 horas. Cache de 7 dias.

---

## Escopo

**Dentro:**

1. **Testes de caracterização** de tudo em [A] antes de mexer no código.
2. `YouTubeService`: métodos curtos e nomeados, constantes, token via `getValidToken()` (fim da lógica de refresh duplicada).
3. `GoogleOAuthProvider`: constantes e `Platform::YOUTUBE` no lugar de strings.
4. `SocialAccount::refreshYouTubeToken`: erro de domínio (`SocialAccountException::tokenRefreshFailed`), como no TikTok.
5. `YouTubeAnalyticsService`: dividir os métodos longos, uma checagem única para "erro de permissão do Google", `match` no lugar do `switch` de períodos, constantes para fallbacks e limites (`[14, 18, 20]`, TTLs de cache, thresholds dos alertas e scores).
6. `AnalyticsController`: enxugar sem mudar status, mensagens nem formato.
7. Remover `DefaultSocialiteProvider` (código morto); plataforma desconhecida no OAuth → 400 `platform_not_supported`.
8. **Correções aprovadas:** Y1, Y2, Y3, Y4, Y6, Y8 (Y5 é corrigido na spec 005; Y7 fica anotado).

---

## Requisitos

- **RF-1** Conectar, renovar token, desconectar, agendar, publicar e consultar analytics continuam com o comportamento de [A].
- **RF-2** As chamadas ao Google continuam iguais (endpoints, parâmetros, escopos, ordem de fallback), exceto: a renovação de token no upload, que passa a acontecer só perto do vencimento (D1); e a métrica de CTR, que deixa de ser consultada (D3).
- **RF-3** O upload envia ao Google pedaços com o tamanho exato configurado (múltiplo de 256 KB), lidos do S3 em stream (Y1).
- **RF-4** Agendar para uma conta do YouTube exige `title` (422 com a mensagem "Para postar no YouTube, você precisa definir um título.") (Y6).
- **RF-5** O dashboard não recomenda trocar thumbnail por "CTR baixo" e o `winnerScore` não depende de CTR (Y3). O campo `ctr` continua na resposta, sempre `0` (o front deve deixar de exibi-lo).
- **RF-6** Falha temporária em `best-times` devolve o fallback sem guardá-lo em cache (Y4).
- **RF-7** Erro 500 do dashboard só inclui o campo `error` com `APP_DEBUG` ligado (Y8).
- **RNF-1** Contrato da API inalterado: rotas, status HTTP, mensagens e formato do JSON (incluindo as chaves em camelCase do dashboard), salvo RF-4 a RF-7.
- **RNF-2** Código no padrão do CLAUDE.md (enxuto, legível, constantes nomeadas, sem código morto).
- **RNF-3** Testes mockam o Google (nenhuma chamada real).

---

## Contrato de API

Sem mudanças. Rotas cobertas pelos testes de caracterização:

| Método | Rota | Permissão |
|---|---|---|
| GET | `/api/social-accounts/google/auth` | `social_accounts.connect` |
| GET | `/api/social-accounts/google/callback` | pública (`state`) |
| DELETE | `/api/social-accounts/{uuid}` | `social_accounts.disconnect` |
| POST | `/api/scheduled-posts` (campos `youtube_*`, `is_short`) | `posts.create` |
| GET | `/api/youtube-categories` | autenticado |
| GET | `/api/youtube-privacy-statuses` | autenticado |
| GET | `/api/analytics/{socialAccount}/dashboard` | autenticado |
| GET | `/api/analytics/{socialAccount}/best-times` | autenticado |

---

## Critérios de aceite

- **CA-1** Existem testes de caracterização (em `tests/Feature/YouTube/`) cobrindo cada item de [A], escritos e **verdes antes** da refatoração.
- **CA-2** Os mesmos testes continuam verdes **depois**; só mudam as asserções das correções (D1–D4, Y1, Y4, Y6, Y8), cada mudança registrada nas Divergências do `tasks.md`.
- **CA-3** O JSON do dashboard para um mesmo cenário mockado é idêntico antes e depois, exceto `ctr`, `winnerScore`, `channelScore` e o alerta de CTR (D3).
- **CA-4** Upload: mesmos metadados enviados ao YouTube; thumbnail enviada só quando não é Short; arquivos apagados só se não estiverem em uso.
- **CA-5** Erros de permissão do Google no dashboard → 401 com `requires_reauth`; outros → 500.
- **CA-6** Isolamento: analytics de uma conta de outro workspace → 404.
- **CA-7** Suíte completa verde; Pint nos arquivos alterados.
- **CA-8** Upload de vídeo de 20 MB com stream que entrega 8 KB por leitura → pedaços enviados com o tamanho configurado.
- **CA-9** `GET /social-accounts/facebook/auth` → 400 `platform_not_supported`.
- **CA-10** Agendar no YouTube sem título → 422; com título → 201.

---

## Fora de escopo

- Y7 (permissão do analytics): anotado para decisão futura.
- Mudar o formato do JSON do analytics para `JsonResource` (mudaria o contrato).
- Esteira compartilhada (`PublishPostJob`): spec 005.

## Problemas encontrados

| # | Problema | Efeito |
|---|---|---|
| Y1 | O upload lê o S3 com `fread($handle, 1 MB)`; em stream de rede o `fread` devolve ~8 KB por vez. | **Provável**: o client do Google envia cada leitura como uma requisição, então vai ~8 KB por requisição em vez de 1 MB (milhares de requisições por vídeo) e fora do múltiplo de 256 KB que o Google recomenda. O teste de caracterização mede o tamanho real das requisições; o efeito no Google (só lentidão ou rejeição de pedaços) precisa ser confirmado num upload real. |
| Y2 | `GET /social-accounts/youtube/auth` (e qualquer plataforma desconhecida) cai no `DefaultSocialiteProvider`, que não implementa a interface. | Erro 500 em vez de 400 `platform_not_supported`. |
| Y3 | A métrica `annotationClickThroughRate` (usada como CTR dos top vídeos) mede cliques em **anotações**, recurso que o YouTube removeu em 2019. A API não oferece CTR de thumbnail. | CTR sempre 0: o alerta "Queda de CTR" aparece para **todo** vídeo com mais de 500 views, e o `winnerScore` perde 30 dos 100 pontos (o alerta de "vídeo vencedor", acima de 75, quase nunca aparece). |
| Y4 | Em `best-times`, o fallback `[14, 18, 20]` em caso de erro é cacheado por 7 dias. | Uma falha temporária (ex.: token) esconde os horários reais por uma semana. |
| Y5 | `PublishPostJob` tem `timeout` de 180 s, menor que o do supervisor do YouTube (300 s). | Vídeos grandes podem ser interrompidos no meio do upload e recomeçar do zero. |
| Y6 | `title` é opcional na validação, mas o YouTube exige título. Mensagens `title.required_if` e `youtube_privacy_status.in` nunca são usadas (as regras não batem). | Post sem título só falha na hora de publicar, com erro do Google. |
| Y7 | Rotas de analytics não têm permissão (`$permissionGroup`). | Qualquer membro do workspace vê as métricas e o faturamento estimado do canal. |
| Y8 | Dashboard em erro 500 devolve `error` com a mensagem crua do Google. | Detalhe interno exposto ao front. |

---

## Decisões (2026-10-06)

- **D1 — Token no upload:** usar `getValidToken()` (renova só perto do vencimento). Aprovado.
- **D2 — Plataforma desconhecida no OAuth:** 400 `platform_not_supported`; `DefaultSocialiteProvider` removido. Aprovado.
- **D3 — CTR (Y3):** a métrica deixa de ser consultada; `ctr` fica `0` na resposta; o alerta de CTR sai; o `winnerScore` passa a ter pesos visualizações 30, tempo assistido 15, retenção 40 e inscritos 15 (as mesmas proporções de antes, sem o CTR, somando 100).
- **D4 — Correções:** Y1, Y2, Y4, Y6 e Y8 corrigidos nesta spec; Y5 e os demais problemas da esteira na spec 005.
- **D5 — Permissão do analytics (Y7):** fica como está por enquanto; decidir depois se vira `analytics.view`.

## Questões em aberto

- **Y7** — permissão do analytics (decisão adiada pelo usuário).
