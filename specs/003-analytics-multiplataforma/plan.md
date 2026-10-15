# Plan 003 — Analytics multiplataforma

## 1. Decisões

| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| P1 | **Strategy + Factory**, no mesmo padrão de `SocialMediaFactory`/`OAuthProviderFactory`: `AnalyticsProviderInterface` + `AnalyticsProviderFactory` (por `Platform`) + um provider por rede em `app/Services/Analytics/`. | `if/match` de plataforma dentro de um service único: viraria um arquivo de 1.500 linhas. |
| P2 | `AnalyticsService` orquestra: resolve o período, valida contra as capacidades da plataforma, cuida do cache e do limite de refresh. Providers só falam com a API e normalizam os dados. Controller fino. | Cache e rate limit no controller (como hoje): mistura HTTP com regra. |
| P3 | Providers usam o **`Http` do Laravel** (REST) em vez da `google/apiclient` no YouTube. É testável com `Http::fake`, igual a Instagram e TikTok. A lib do Google continua no `YouTubeService` (upload). | Manter a lib do Google no analytics: não dá para mockar sem infraestrutura nova de testes. |
| P4 | `App\Support\Analytics\AnalyticsPeriod` (período atual/anterior, granularidade) e `MetricsCalculator` (variação, engajamento, nota, melhores horários): funções puras com teste unitário. Ficam em `Support/`, como o `DynamicFilter`. | Repetir os cálculos em cada provider. |
| P5 | O contrato é montado pelos providers como array normalizado e devolvido por `AnalyticsDashboardResource`/`BestTimesResource` (envelope `data`). | Devolver `response()->json($array)` cru: fora da convenção de `JsonResource`. |
| P6 | Erros por `AnalyticsError` + `AnalyticsException` (`reauthRequired()` 409, `providerUnavailable()` 502). Exceções de HTTP dos providers são convertidas aí; a mensagem original vai só para o log. | 401 para reauth (como hoje): colide com a semântica de "sessão expirada" do front. |
| P7 | Snapshot em `social_account_metric_snapshots` (1 linha por conta por dia, `unique(social_account_id, date)`, `updateOrCreate` → idempotente). Só métricas de conta, nunca por vídeo (é o que manteria o custo baixo). `MassPrunable` com 400 dias, removido pelo `model:prune` diário. Comando `analytics:snapshot` às 03:00 despacha um job por conta na **fila da plataforma**. | Snapshot por vídeo: N vídeos × dias, cresce sem limite. |
| P8 | TikTok OAuth real no `TikTokOAuthProvider` (mesma interface). Refresh e revogação no `SocialAccount`, ao lado de YouTube/Instagram. Credenciais em `config/services.php` → `tiktok`. | Pacote `socialiteproviders/tiktok`: dependência nova para 3 chamadas HTTP. |
| P9 | Instagram: períodos limitados a 30 dias (limite da API para métricas únicas como alcance). Somar janelas de 30 dias daria alcance errado (contagem única). | Fatiar em janelas e somar: números imprecisos. |
| P10 | TikTok: "período" = vídeos **publicados** no período (métricas acumuladas), explicado em `notes`. Crescimento de seguidores/curtidas vem dos snapshots. | Estimar views diárias: não há dado na API. |

## 2. Modelo de dados

`social_account_metric_snapshots`: `id`, `social_account_id` (FK cascade), `date` (date), `followers` (unsigned bigint, null), `following` (null), `media_count` (null), `total_likes` (null), `timestamps`. Índice único (`social_account_id`, `date`). Compatível com PostgreSQL e SQLite.

## 3. Arquivos

- **Novos:** `app/Contracts/AnalyticsProviderInterface.php`; `app/Services/Factories/AnalyticsProviderFactory.php`; `app/Services/Analytics/{AnalyticsService,YouTubeAnalyticsProvider,InstagramAnalyticsProvider,TikTokAnalyticsProvider}.php`; `app/Support/Analytics/{AnalyticsPeriod,MetricsCalculator}.php`; `app/Enums/Exceptions/AnalyticsError.php`; `app/Exceptions/AnalyticsException.php`; `app/Http/Requests/AnalyticsDashboardRequest.php`; `app/Http/Resources/{AnalyticsDashboardResource,BestTimesResource}.php`; `app/Models/SocialAccountMetricSnapshot.php`; migration; `app/Jobs/SnapshotSocialAccountMetricsJob.php`; `app/Console/Commands/SnapshotSocialAccountsCommand.php`.
- **Alterados:** `AnalyticsController` (fino); `SocialAccount` (relação de snapshots, refresh/revogação TikTok); `TikTokOAuthProvider` (real); `InstagramOAuthProvider` (escopo); `config/services.php` (`tiktok`); `routes/console.php` (agendamentos).
- **Removido:** `app/Services/YouTubeAnalyticsService.php` (substituído pelo provider).

## 4. Efeitos colaterais

- Contas Instagram precisam reconectar (novo escopo). Até lá, o dashboard delas responde 409.
- Contas TikTok stub já criadas (`tiktok_stub_*`) passam a responder 409 no dashboard (token falso). Precisam ser desconectadas e conectadas de novo com o OAuth real.
- Novas variáveis de ambiente: `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`, `TIKTOK_REDIRECT_URI` (= `{APP_URL}/api/social-accounts/tiktok/callback`). O `.env.example` não é alterado sem autorização; fica registrado aqui.
- Novo agendamento diário (03:00) e jobs nas filas `instagram`/`tiktok`.

## 5. Testes

- Unit: `AnalyticsPeriod` (presets, custom, período anterior, limites) e `MetricsCalculator` (variação com anterior 0 → null, engajamento, nota, melhores horários).
- Feature: dashboard por plataforma com `Http::fake` (contrato completo); 409 reauth; 502 sem vazar mensagem e sem cache; 422 período acima do limite; 429 refresh; 404 outro workspace; best-times nas três; snapshot idempotente + poda; OAuth TikTok (redirect sem credenciais, troca de code, refresh, revogação).

## 6. Riscos

- **Mudança de API da Meta/TikTok/Google** (versões, métricas descontinuadas): versões centralizadas em constantes por provider.
- **Quota do YouTube** (10.000 unidades/dia por projeto): Analytics API não consome quota da Data API. A Data API (`videos.list`) custa 1 unidade por chamada; com cache de 6h, ok.
- **Instagram**: insights por mídia é 1 chamada por post; limitado aos 12 melhores do período + cache de 3h.
