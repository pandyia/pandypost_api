# Tasks 003 — Analytics multiplataforma

> Legenda: `[x]` feito · `[ ]` pendente · `[-]` fora desta fase.

## Fase 1 — Núcleo

- [x] T1 `AnalyticsPeriod` + `MetricsCalculator` — teste: unit `tests/Unit/Analytics/*`
- [x] T2 `AnalyticsError`/`AnalyticsException`, `AnalyticsProviderInterface`, `AnalyticsProviderFactory`, `AnalyticsService`, `AnalyticsDashboardRequest`, Resources, `AnalyticsController` — teste: 422/429/404/409/502
- [x] T3 `YouTubeAnalyticsProvider` (REST) + remover `YouTubeAnalyticsService` — teste: contrato YouTube com `Http::fake`

## Fase 2 — Instagram e TikTok

- [x] T4 Snapshot: migration, model (prunable), job, comando, agendamento — teste: idempotência e poda
- [x] T5 `InstagramAnalyticsProvider` + escopo de insights no OAuth — teste: contrato Instagram, 409 sem escopo
- [x] T6 TikTok OAuth real (provider, refresh, revogação, config) — teste: OAuth com `Http::fake`
- [x] T7 `TikTokAnalyticsProvider` — teste: contrato TikTok, seguidores via snapshot
- [x] T8 `best-times` nas três plataformas — teste: por plataforma + fallback

## Fase 3 — Fechamento

- [x] T9 Pint + suíte completa + `route:list`

## Fora desta fase

- [-] Publicação real no TikTok, receita do YouTube, demografia, permissão das rotas (ver spec)

## Divergências

- **T4:** o `SocialAccountMetricSnapshot` grava `date` sempre como `Y-m-d` (mutator). No SQLite dos testes o cast `date` gravava `Y-m-d 00:00:00`, o que quebrava a comparação de datas e a idempotência do `updateOrCreate`.
- **T5:** o token do Instagram vai como parâmetro `access_token`, mesmo padrão do `InstagramService` (não como header).
- **T5/T8:** no Instagram, melhores horários usam **interações** públicas (curtidas + comentários) e não views: views por post custariam 1 chamada por mídia. A resposta informa `metric: interactions` (capacidade `best_times_metric`).
- **T7:** criado `App\Services\Analytics\SnapshotHistory` (leitura dos snapshots), compartilhado por Instagram e TikTok. Não estava no plano; evita duplicar a mesma consulta nos dois providers.
- **Testes:** o teste antigo "retorna 400 para plataformas sem suporte a analytics" (`SocialAccountTest`) foi removido: o comportamento mudou por esta spec (TikTok agora é suportado). O cenário de conta stub ficou em `TikTokAnalyticsTest`.
- **Pint (T9):** reformatou por inteiro os arquivos tocados (só estilo).
