# Tasks 004 — Refatoração do Instagram

> Legenda: `[x]` feito · `[ ]` pendente.

- [x] Testes de caracterização: conexão, refresh, warmup, publicação (container novo, existente FINISHED/ERROR/IN_PROGRESS), job (FINISHED/ERROR/em andamento), isolamento — teste: CA-1
- [x] `InstagramService` dono da Graph API + erros de domínio + mensagens pt-BR (I7) — teste: CA-3, CA-5
- [x] `CheckInstagramContainerJob` usando o service, prazo + `failed()` (I1, I2) — teste: CA-9
- [x] Permalink (I3) — teste: CA-10
- [x] `InstagramOAuthProvider`: constantes, escopos (I6), falha do token longo (I5) — teste: CA-6, CA-7
- [x] `SocialAccount::refreshToken()` + `instagram:refresh-tokens` agendado (I4) — teste: CA-11
- [x] `WarmupPostsCommand` e `InstagramPayloadBuilder` enxutos — teste: CA-4
- [x] Pint + suíte completa — teste: CA-8

## Divergências

- Testes de caracterização escritos e verdes **antes** da refatoração (30 testes). As asserções que mudaram depois, todas por correção aprovada:
  - publicação pelo job apaga a mídia (I1); container com erro na publicação falha o job, no warmup só é descartado; consulta inicial após 5 s (antes 2 s) e prazo de 30 min (I2);
  - link pelo `permalink` (I3); escopos sem mensagens (I6); falha no token longo recusa a conexão (I5);
  - recusa de container ou publicação lança `ScheduledPostException` com mensagem "Falha ao publicar no Instagram: {motivo}" em vez de gravar o JSON cru (I7). Na publicação, quem marca o post como `failed` passa a ser o `failed()` do job, depois das tentativas.
- Comando novo `instagram:refresh-tokens` agendado às 03:00 (I4).
- `GenericPayloadBuilder` removido: ficou sem uso depois que o TikTok passou a estender a classe-base.
- `WarmupPostsCommand` recebe o `InstagramService` direto (antes pegava pela factory) e não repete o try/catch que o `prepare()` já faz.
- O Pint removeu imports sem uso de `routes/console.php`.
