# Spec 003 — Analytics multiplataforma (YouTube, Instagram e TikTok)

**Status:** aprovada para execução (decisões do usuário em 2026-10-14) · **Front:** `pandypost_ui/specs/005-analytics-multiplataforma/`

## Contexto

O dashboard de analytics só funciona para YouTube (`AnalyticsController` responde 400 para outras plataformas). O usuário precisa acompanhar as três redes com métricas precisas e profissionais.

**Spec reversa do comportamento atual (área crítica: OAuth social):**

- `GET /api/analytics/{socialAccount}/dashboard` — só YouTube; usa a lib `google/apiclient`; cache de 6h; `refresh=1` limitado a 1x/10min por conta+usuário (429); erro de permissão do Google → 401 `requires_reauth`; outros erros → 500 com `$e->getMessage()` cru (VULN-06).
- `GET /api/analytics/{socialAccount}/best-times` — YouTube: média de views por hora de publicação dos últimos 50 vídeos (fuso `America/Sao_Paulo`); outras plataformas e falhas → `[14, 18, 20]` fixo.
- Nenhuma das duas rotas exige permissão (VULN-06, questão em aberto na spec 002).
- **Defeitos do cálculo atual:**
  - "CTR" usa `annotationClickThroughRate`: as anotações do YouTube foram descontinuadas em 2019, então o valor é sempre 0. O alerta "Queda de CTR" dispara para qualquer vídeo com mais de 500 views.
  - "Receita estimada" exige o escopo `yt-analytics-monetary.readonly`, que o app não pede. A chamada sempre cai no fallback e mostra 0.
  - Shorts detectados por duração ≤ 60s; desde out/2024 Shorts podem ter até 3 min.
  - O "Winner Score" depende do CTR inexistente.
- **Instagram:** o OAuth pede `instagram_business_basic`, `instagram_business_content_publish` e `instagram_business_manage_messages`. **Não pede** `instagram_business_manage_insights`, obrigatório para insights.
- **TikTok:** `TikTokOAuthProvider` é stub: cria conta falsa (`mock_tiktok_code`) em qualquer ambiente (OAUTH-02). `TikTokService` (publicação) também é stub.

### O que cada API entrega (verificado na documentação oficial em 2026-10-14)

| | YouTube Analytics v2 + Data v3 | Instagram Graph (Instagram Login) | TikTok Display API v2 |
|---|---|---|---|
| Histórico | Completo, desde a criação do canal | Insights de conta com `since/until` limitado a 30 dias por consulta; atraso de até 48h | **Nenhum**: só totais acumulados atuais |
| Conta | views, watch time, duração média, % assistida, inscritos ganhos/perdidos, curtidas, comentários, compartilhamentos | views, reach, accounts_engaged, total_interactions, likes, comments, shares, saves, follows_and_unfollows, profile_links_taps (`impressions` descontinuado) | follower_count, following_count, likes_count, video_count |
| Série diária | Sim (qualquer métrica) | Só `reach` (`time_series`) | Não |
| Por conteúdo | Sim | views, reach, likes, comments, shares, saved, total_interactions; Reels: tempo médio assistido | view/like/comment/share_count acumulados por vídeo |
| Impressões/CTR | **Não disponível na API** | — | — |

## Escopo

1. Dashboard único para as três plataformas, com contrato normalizado.
2. YouTube reescrito com métricas reais (sem CTR inexistente, com engajamento, % assistida e Shorts corretos).
3. Instagram com insights reais (exige reconexão das contas: novo escopo).
4. TikTok com OAuth real (Login Kit) e métricas pela Display API. **Publicação no TikTok continua stub** (fora do escopo).
5. Snapshot diário por conta (Instagram e TikTok) para dar histórico de seguidores e totais que as APIs não guardam.
6. Melhores horários calculados por plataforma, com dados por hora.

## Requisitos

- **RF-1** `GET /api/analytics/{socialAccount}/dashboard` responde para `youtube`, `instagram` e `tiktok` no **mesmo formato** (ver Contrato). Campos que a plataforma não oferece simplesmente não aparecem (nunca valor inventado ou zero falso).
- **RF-2** Cada plataforma declara suas **capacidades**: períodos aceitos, intervalo máximo, se tem série diária, receita, watch time. O front monta os filtros a partir disso.
  - YouTube: `last_7_days`, `last_28_days`, `last_90_days`, `this_month`, `last_month`, `last_365_days`, `lifetime`, `custom` (sem limite).
  - Instagram: `last_7_days`, `last_28_days`, `custom` (máx. 30 dias, limite da API para métricas únicas como alcance).
  - TikTok: `last_7_days`, `last_28_days`, `last_90_days`, `custom` (máx. 365 dias).
- **RF-3** Toda métrica de visão geral traz valor atual, valor do período anterior de mesmo tamanho e variação %. Se o período anterior não tem dado, a variação é `null` (não "+100%").
- **RF-4** Métricas do TikTok por conteúdo são **acumuladas desde a publicação** e consideram os vídeos **publicados no período**. Isso é dito na resposta (`notes`). "Seguidores ganhos" e "curtidas ganhas" vêm da diferença entre snapshots e só aparecem quando há snapshot nas duas pontas do período.
- **RF-5** Taxa de engajamento por conteúdo = (curtidas + comentários + compartilhamentos + salvos) ÷ visualizações. Salvos só no Instagram.
- **RF-6** Nota da conta (0–100): 50% engajamento médio dos conteúdos do período comparado à referência da plataforma (YouTube 4%, Instagram 3%, TikTok 6%; teto em 2× a referência); 30% crescimento de visualizações contra o período anterior (−50% ou pior = 0; +50% ou melhor = 30; sem base de comparação = 15, neutro); 20% frequência de postagem (`daysSinceLastActivity`: até 3 dias = 20, até 7 = 13, até 15 = 6, mais que isso = 0).
- **RF-7** Alertas gerados a partir de dados reais: queda ou alta de visualizações ≥ 20%, conteúdo de destaque (engajamento ≥ 1,5× a referência), conta sem postar há 7+ dias, variação negativa de seguidores.
- **RF-8** Token sem permissão de leitura (escopo faltando, revogado ou expirado sem refresh) → **409** `analytics_reauth_required`. O front mostra "Reconectar conta". Falha da API externa → **502** `analytics_provider_unavailable` com mensagem genérica (nada de `$e->getMessage()` na resposta). Período inválido ou acima do limite da plataforma → **422** de validação.
- **RF-9** Cache do dashboard por conta + período: YouTube 6h, Instagram 3h, TikTok 1h. Respostas de erro nunca vão para o cache. `refresh=1` mantém o limite de 1x a cada 10 min (429).
- **RF-10** `GET /api/analytics/{socialAccount}/best-times` passa a funcionar para as três plataformas: média de visualizações por hora de publicação dos últimos conteúdos (até 50) no fuso `America/Sao_Paulo`. Responde `best_hours` (top 3, mantido), `by_hour` (24 posições) e `sample_size`. Com menos de 5 conteúdos, `best_hours` usa o fallback `[14, 18, 20]` e `is_fallback = true`.
- **RF-11** Snapshot diário (03:00) de cada conta Instagram e TikTok: seguidores, seguindo, total de mídias e, no TikTok, total de curtidas. Um registro por conta por dia, idempotente. Snapshots com mais de 400 dias são removidos automaticamente.
- **RF-12** OAuth TikTok real: Login Kit web com escopos `user.info.basic`, `user.info.profile`, `user.info.stats` e `video.list`; troca de code; refresh automático (access token de 24h, refresh de 365 dias); revogação ao desconectar. Sem credenciais configuradas, `GET /social-accounts/tiktok/auth` responde erro de inicialização (nada de conta falsa).
- **RF-13** OAuth Instagram passa a pedir `instagram_business_manage_insights`.

## Contrato de API

`GET /api/analytics/{socialAccount}/dashboard?date_range=last_28_days&start_date=&end_date=&refresh=0`

```json
{
  "platform": "instagram",
  "period": { "start": "2026-09-16", "end": "2026-10-13", "previous_start": "2026-08-19", "previous_end": "2026-09-15", "granularity": "day", "date_range": "last_28_days" },
  "capabilities": { "date_ranges": ["last_7_days", "last_28_days", "custom"], "max_range_days": 30, "has_daily_series": true, "has_revenue": false, "has_watch_time": false },
  "overview": [
    { "key": "views", "label": "Visualizações", "format": "number", "value": 12034, "previous": 9800, "trend": 22.8, "description": "..." }
  ],
  "time_series": {
    "dates": ["2026-09-16", "..."],
    "series": [ { "key": "reach", "label": "Alcance", "format": "number", "type": "area", "current": [1, 2], "previous": [1, 2] } ]
  },
  "breakdowns": [ { "key": "content_type", "label": "Visualizações por formato", "items": [ { "key": "REEL", "label": "Reels", "value": 900 } ] } ],
  "top_content": {
    "columns": [ { "key": "views", "label": "Visualizações", "format": "number" } ],
    "items": [ { "id": "...", "title": "...", "thumbnail_url": "...", "permalink": "...", "published_at": "2026-09-20T18:00:00Z", "format": "reel", "metrics": { "views": 10, "engagement_rate": 4.2 } } ]
  },
  "score": { "value": 72, "engagement": 40, "growth": 20, "frequency": 12 },
  "alerts": [ { "type": "success", "title": "...", "message": "..." } ],
  "notes": ["Os dados do Instagram podem ter atraso de até 48 horas."],
  "generated_at": "2026-10-14T18:00:00Z"
}
```

As duas respostas vêm envelopadas em `{ "data": ... }` (padrão `JsonResource`). O front atual não usa `best-times`, então a mudança de formato não quebra nada.

- `format`: `number` | `percent` | `duration` (segundos) | `hours` | `currency`.
- `trend` e `previous` podem ser `null` (RF-3).
- Erros: `409 {error: "analytics_reauth_required"}`, `502 {error: "analytics_provider_unavailable"}`, `422` validação, `429` refresh, `404` conta de outro workspace.

`GET /api/analytics/{socialAccount}/best-times`

```json
{ "best_hours": [18, 20, 12], "by_hour": [ { "hour": 0, "average": 0, "posts": 0 } ], "metric": "views", "sample_size": 37, "is_fallback": false, "timezone": "America/Sao_Paulo" }
```

## Critérios de aceite

1. Dashboard responde 200 no formato do contrato para contas YouTube, Instagram e TikTok, com as APIs externas mockadas.
2. YouTube não expõe CTR, nem receita sem o escopo monetário. Retenção usa `averageViewPercentage`. Shorts = duração ≤ 180s.
3. Instagram sem o escopo de insights (erro OAuth da Meta) → 409 `analytics_reauth_required`.
4. TikTok calcula visualizações/curtidas dos vídeos publicados no período e seguidores ganhos via snapshot; sem snapshot, "seguidores ganhos" não aparece.
5. Período acima do limite da plataforma → 422. Período anterior sem dado → `trend: null`.
6. Falha da API externa → 502 sem detalhes internos; nada é cacheado.
7. `best-times` funciona nas três plataformas; com menos de 5 conteúdos, `is_fallback: true`.
8. Comando de snapshot cria um registro por conta Instagram/TikTok por dia, sem duplicar ao rodar 2x; a poda remove registros com mais de 400 dias.
9. OAuth TikTok: troca de code, criação da conta com `open_id` e tokens, refresh e revogação, tudo testado com `Http::fake`. Sem credenciais → erro, sem conta falsa.
10. Conta de outro workspace → 404 (já coberto). Suíte inteira verde.

## Fora de escopo

- Publicação real no TikTok (`TikTokService` continua stub — exige escopo `video.publish` e auditoria do app).
- Receita do YouTube (exige o escopo `yt-analytics-monetary.readonly` e reconexão de todos os canais).
- Impressões/CTR de miniatura do YouTube (não existem na API).
- Demografia de público (Instagram exige 100+ seguidores; fica para uma próxima spec).
- Permissão nas rotas de analytics (VULN-06 / Q3 da spec 002, ainda em aberto).
- Comparar contas entre si ou visão consolidada do workspace.

## Questões em aberto

1. As referências de engajamento do RF-6 (YouTube 4%, Instagram 3%, TikTok 6%) são médias de mercado e podem ser ajustadas depois.
2. Fuso horário: segue fixo `America/Sao_Paulo` (como hoje). Usar o fuso do usuário fica para quando ele existir no cadastro.
