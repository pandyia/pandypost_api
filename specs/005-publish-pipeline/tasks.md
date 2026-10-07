# Tasks 005 — Esteira de publicação

> Legenda: `[x]` feito · `[ ]` pendente.

- [x] Testes de caracterização do `PublishPostJob` e do cancelamento (comportamento atual) — teste: suíte verde antes da mudança
- [x] `ScheduledPost::markAsPublished`, `markAsFailed`, `mediaPaths` — teste: CA-3
- [x] `StorageService::deletePostMedia` — teste: CA-1, CA-2
- [x] `PublishPostJob`: base comum, erros de domínio, sem `timeout`, `backoff` — teste: CA-2, CA-4, CA-5
- [x] `ScheduledPostService::cancel` com `deletePostMedia` — teste: CA-1, CA-7
- [x] TikTok usando a base comum — teste: CA-6
- [x] Pint + suíte completa

## Divergências

- **E7 (achado na implementação):** o `deleteIfUnused` só conferia o `media_path` dos outros posts; a thumbnail, também compartilhada no agendamento múltiplo, era apagada mesmo em uso. Agora a checagem cobre `payload->thumbnail_path` (funciona em PostgreSQL e SQLite). Teste: "mantém a thumbnail que outro post ativo ainda usa".
- `StorageService::deleteMany` removido (ficou sem uso) e `deleteIfUnused` virou privado: o único ponto de entrada é `deletePostMedia`.
- Trait `App\Jobs\Concerns\PollsPlatformStatus` (prazo, espera crescente) extraída para os jobs de acompanhamento do TikTok e do Instagram, que tinham o mesmo código.
- Helpers de teste generalizados em `tests/Pest.php`: `createSocialAccount`, `createScheduledPost`, `uploadVideo`, `PacketStream` e `fakeGoogleApi`/`googleResponse`.
- Teste de isolamento do cancelamento usa `app('auth')->forgetGuards()`: dentro de um mesmo teste, o guard do Sanctum guarda o usuário da requisição anterior (não é bug da aplicação).
- **Apontamento:** o endpoint `POST /api/scheduled-posts/upload-url` não tem teste.
- Resultado: suíte completa com 255 testes verdes em 2026-10-06.
