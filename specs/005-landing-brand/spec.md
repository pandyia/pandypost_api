# 005 — Tema padrão da landing (definido pelo super admin)

**Status:** aprovada (2026-10-16; auditoria fora do escopo) · **Par no front:** `pandypost_ui/specs/008-landing-brand-admin`

## Contexto
O front tem temas visuais (`pop`, `violeta`, `coral`, `bambu`, `rose`). Cada usuário escolhe o tema da área logada, mas a **landing é pública e tem uma identidade só**, hoje fixa no código do front. Trocar essa identidade exige deploy. O super admin (equipe do Pandypost) precisa escolher o tema da landing sem mexer em código.

## Escopo
- Guardar no banco o tema da landing.
- Rota pública de leitura (a landing é vista sem login).
- Rota de escrita exclusiva do super admin.

## Requisitos
- **RF-1** `GET /api/landing-settings` é pública e devolve o tema atual da landing.
- **RF-2** Sem valor salvo, devolve o padrão (`pop`). Valor salvo que deixou de ser válido também cai no padrão, sem erro.
- **RF-3** `PUT /api/admin/landing-settings` altera o tema; só super admin. Dono de workspace, mesmo com todas as permissões, recebe 403 (mesma regra de `/admin/plans`).
- **RF-4** O tema só pode ser um dos valores aceitos (enum no backend). Qualquer outro valor → 422.
- **RF-5** Nenhum campo além de `brand` é aceito ou devolvido; nada do usuário que alterou vaza na rota pública.
- **RF-6** A escrita é idempotente: repetir o mesmo `PUT` mantém o mesmo estado.

## Contrato de API
`GET /api/landing-settings` (sem autenticação, `throttle:45,1`)
- 200 `{ "data": { "brand": "pop" } }`

`PUT /api/admin/landing-settings` `{ "brand": "coral" }` (`auth:sanctum`, `verified`, `super_admin`)
- 200 `{ "message": "Tema da landing atualizado.", "data": { "brand": "coral" } }`
- 401 não autenticado · 403 não é super admin · 422 `brand` ausente ou fora da lista

Valores aceitos: `pop`, `violeta`, `coral`, `bambu`, `rose`. **Adicionar um tema no front exige adicionar o valor no enum da API** (registrar em ambos os specs).

## Critérios de aceite
- Banco vazio: `GET` devolve `pop`.
- Super admin troca para `coral`; `GET` público passa a devolver `coral`.
- Usuário comum (inclusive dono de workspace) → 403 e nada muda no banco.
- Sem token → 401. `brand` inválido ou ausente → 422 e nada muda.
- Valor inválido gravado direto no banco → `GET` devolve `pop`.

## Fora de escopo
Cores livres, outros textos/seções da landing, modo claro/escuro padrão, histórico de alterações, cache.

## Questões em aberto
1. ~~Registrar a troca no log de auditoria?~~ Decidido: fora do escopo.
