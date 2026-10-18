# 009 — Canais de notificação por família (e-mail × no app)

## Contexto
Hoje a preferência por família (`invite`, `posts`, `social_accounts`) controla **só o e-mail**; central (database) e tempo real (broadcast) ficam sempre ligados (spec 007, D-3). O usuário quer escolher **separadamente** se recebe e-mail e/ou o aviso no app (sino + toast em tempo real).

## Escopo
Segundo canal configurável por família: **no app** (database + broadcast). E-mail não muda.

## Requisitos
- RF-1: `GET /api/notification-preferences` devolve, além dos campos atuais, `in_app: { invite, posts, social_accounts }` (padrão `true`).
- RF-2: `PATCH` aceita `in_app.invite|posts|social_accounts` (boolean, parcial). Campos soltos continuam sendo o **e-mail** (sem quebra). `in_app.billing` e `in_app.post_published` são proibidos/ignorados: billing é fixo e `post_published` já é só in-app (campo solto, opt-in).
- RF-3: família com `in_app = false` → a notificação **não** é gravada nem transmitida (`via()` sem `database`/`broadcast`). O e-mail segue a preferência de e-mail.
- RF-4: as duas desligadas → nada é enviado.
- RF-5: billing continua sempre e-mail + in-app. Tipos novos `workspace_invite_accepted/declined` seguem a família `invite`.

## Contrato
Ver RF-1/RF-2. Erros: 422 em valor não booleano ou `in_app.billing`.

## Critérios de aceite
Testes Pest: padrão, PATCH parcial, 422, `via()` por combinação (app on/off × e-mail on/off), billing intocado, isolamento entre usuários.

## Fora de escopo
Push do navegador/sistema operacional (exigiria permissão do usuário e service worker). Preferência por workspace.

## Questões em aberto / riscos
- Sem registro no banco quando o app está desligado, a deduplicação (`dedupe_key`) não enxerga envios antigos: com e-mail ligado e app desligado, um evento repetido pode repetir o e-mail.
- Sem o aviso no app, o remetente de um convite não recebe a notificação de aceito/recusado; a grid ainda atualiza pelo evento `InviteUpdated` do workspace.

## Adendo — Post publicado com dois canais
- RF-6: `post_published` vira família como as outras. **E-mail** (campo solto `post_published`, padrão **ligado**) e **no app** (`in_app.post_published`, padrão **desligado**, opt-in), ambos opcionais. A notificação só é criada/enviada se ao menos um canal estiver ligado; só o autor do post é avisado.
- Mudança de significado: antes `post_published` era o aviso no app. Sem migração nem seeder: o recurso ainda não foi lançado e o banco de desenvolvimento já foi acertado à mão. Se um ambiente real tiver o formato antigo, o acerto de dados é um comando Artisan avulso.
- Risco: com o padrão (e-mail ligado, app desligado) não há linha no banco, então a deduplicação não protege contra e-mail repetido se o evento de publicação for reprocessado. Hoje o gancho dispara uma vez por publicação.
- O volume de e-mails sobe: todo post publicado gera e-mail para quem não mexer na preferência.
