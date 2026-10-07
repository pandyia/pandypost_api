# Tasks 003 — Refatoração do YouTube

> Legenda: `[x]` feito · `[ ]` pendente.

- [x] Ponto de injeção `app(Client::class)` + helper `fakeGoogleApi` — teste: suíte verde
- [x] Testes de caracterização: conexão, refresh, revogação, agendamento, upload, thumbnail/Short, dashboard (snapshot), best-times, isolamento — teste: CA-1
- [x] `YouTubeService` enxuto, token via `getValidToken()`, pedaços exatos de 8 MB — teste: CA-4, CA-8
- [x] `GoogleOAuthProvider` + factory (`default` → 400) + remover `DefaultSocialiteProvider` — teste: CA-9
- [x] `refreshYouTubeToken` com erro de domínio — teste: CA-1
- [x] `YouTubeAnalyticsService` enxuto + CTR (D3) + best-times sem cache de fallback (Y4) — teste: CA-3, CA-5
- [x] `AnalyticsController` enxuto + `error` só com debug (Y8) — teste: CA-5, CA-6
- [x] Título obrigatório para YouTube + mensagem `.enum` — teste: CA-10
- [x] Pint + suíte completa — teste: CA-7

## Divergências

- Testes de caracterização escritos e verdes **antes** da refatoração (31 testes). As asserções que mudaram depois, todas por correção aprovada:
  - token: "renova a cada upload" → "usa o token sem renovar enquanto é válido" + "renova o token vencido" (D1);
  - snapshot do dashboard: só mudaram o alerta (sai "Queda de CTR 0%", entra "Vídeo Vencedor"), os `winnerScore` (66 → 94 e 61 → 86, mesma proporção na escala de 100) e o `channelScore` (51 → 65) (D3), conferido campo a campo;
  - best-times não guarda o fallback em cache (Y4); erro 500 sem `error` fora do debug (Y8); título obrigatório (Y6); erro de domínio com mensagem amigável no upload.
- **Não previsto no plano:** erros do upload mapeados para mensagens pt-BR (`quotaExceeded`, `uploadLimitExceeded`, `authError`, `forbidden`), no mesmo padrão do TikTok.
- `YouTubeAnalyticsService` usa `YouTubeService::clientFor()` em vez de montar o client do Google de novo. Cache do dashboard passou de `v3` para `v4` para os scores novos aparecerem na hora.
- Trait `ResolvesAccountOwner` para "quem é o dono da conta" no callback, usada pelos 3 providers OAuth (o trecho estava repetido em cada um).
- `SocialAccount::refreshToken()` público e `saveRefreshedToken()` único para as 3 plataformas (antes eram 3 cópias da lógica).
- `GoogleOAuthProvider` captura só `Exception` na troca do código: um erro de programação (`TypeError`) não pode virar "falha no token".
- **Apontamentos:** `AnalyticsController` continua fora do `BaseController` e sem `JsonResource` (mudar alteraria o contrato); Y7 (permissão do analytics) segue pendente; as mensagens de validação padrão aparecem como `validation.string` (falta tradução pt-BR das mensagens de validação do Laravel na aplicação toda).
