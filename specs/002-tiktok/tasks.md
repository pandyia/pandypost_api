# Tasks 002 — Integração com o TikTok

> Checklist derivado do [`plan.md`](plan.md). Legenda: `[x]` feito · `[ ]` pendente.

- [x] Erros de domínio: `ScheduledPostError::PUBLISH_FAILED`, `SocialAccountError::TOKEN_REFRESH_FAILED` e `MISSING_PERMISSIONS` + factories — teste: CA-4, CA-7
- [x] Enum `TikTokPrivacyLevel` + `Rule::enum` no `StoreScheduledPostRequest` — teste: CA-12
- [x] `TikTokPayloadBuilder` com loop dos booleanos — teste: CA-12
- [x] `TikTokOAuthProvider`: constantes, escopos `user.info.basic,video.publish`, checagem do escopo concedido — teste: CA-1, CA-2, CA-3, CA-13
- [x] `SocialAccount`: refresh com erro de domínio + revogação no TikTok — teste: CA-4, CA-16
- [x] `TikTokService`: pedaços (D2), leitura exata (D6), MIME (D3), erros de domínio com mensagens pt-BR (D5) — teste: CA-5, CA-6, CA-7
- [x] `CheckTikTokPostStatusJob`: id público (D1), prazo de 1 h + `failed()` (D4), limpeza de thumbnail — teste: CA-8, CA-9, CA-10
- [x] `ScheduledPost::getPlatformPostUrl` → `@/video/{id}` — teste: CA-11
- [x] `config/services.php` (indentação) e `routes/web.php` (remove rota `/`) — teste: suíte completa
- [x] Migrar testes para `tests/Feature/TikTok/` em Pest e remover `TikTokIntegrationTest.php` — teste: CA-14, CA-15
- [x] Pint nos arquivos alterados + suíte completa verde — teste: CA-15

## Divergências

- Helpers `createTikTokAccount()` e `createTikTokPost()` adicionados em `tests/Pest.php` (não previstos no plano), usados pelos 4 arquivos de teste.
- `fetchProfile` deixou de pedir o campo `union_id` (não era usado).
- O `pint --dirty` reformatou trechos pré-existentes dos arquivos tocados (aspas, `! `, concatenação, imports). Só estilo, sem mudança de comportamento.
- `routes/web.php` voltou ao estado do `develop` (o Pint também removeu o `use Route` que ficou sem uso).
- Resultado: 45 testes do TikTok e 167 na suíte completa, todos verdes em 2026-10-05. O teste de pedaços falha se a leitura voltar a usar `fread` (checado trocando a linha e revertendo).
