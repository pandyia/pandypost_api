# 006 — Perfil do usuário (dados, foto e senha)

**Status:** aprovada (2026-10-16) · **Par no front:** `pandypost_ui/specs/010-perfil-api` · **Contrato de origem:** `pandypost_ui/BACKEND_PERFIL.txt`

## Contexto
A tela "Meu perfil" do front já chama endpoints `/me` que **não existem** na API (hoje 404/405). O `GET /me` também não devolve `avatar`, `phone`, `bio`, `timezone` nem `created_at`, e `users` não tem essas colunas. A foto precisa seguir o sistema de arquivos da API: S3 por URL pré-assinada, via `StorageService`.

Spec reversa (comportamento atual): não há endpoint de perfil próprio; `PATCH /users/{uuid}` está quebrado e fora de escopo (spec 002 D3); só existe o reset de senha por token (`AuthService::resetPassword`).

## Escopo
Editar dados pessoais, enviar/trocar/remover a foto e trocar a senha, tudo do próprio usuário autenticado. `GET /me` passa a devolver os campos novos.

## Requisitos
- **RF-1** `PATCH /api/me` atualiza `name`, `phone`, `bio` e `timezone`. `email`, `uuid`, `is_super_admin`, `access_id` e qualquer outro campo enviado são ignorados (spec 002 RF-1/D4).
- **RF-2** `POST /api/me/avatar/upload-url` devolve uma URL pré-assinada de PUT para um arquivo em `users/{uuid}/avatars/{uuid}.{ext}`. Aceita `image/jpeg`, `image/png` e `image/webp`.
- **RF-3** `PUT /api/me/avatar` recebe o `path` do upload feito, confirma que o arquivo é do próprio usuário, existe, é imagem permitida e tem no máximo 5 MB; só então grava e **apaga a foto anterior**. Arquivo inválido é apagado do storage e a resposta é de erro.
- **RF-4** `DELETE /api/me/avatar` apaga o arquivo e limpa o campo; sem foto, responde sucesso (idempotente).
- **RF-5** `PUT /api/me/password` exige a senha atual, a nova (mín. 8, 1 maiúscula, 1 número, confirmada, diferente da atual) e **revoga os outros tokens**, mantendo o atual válido.
- **RF-6** `GET /me` e todas as respostas acima devolvem o usuário completo no formato do `MeResource`, com `avatar` (URL assinada válida por ~6 dias, ou `null`), `phone`, `bio`, `timezone` e `created_at`/`email_verified_at` em ISO 8601.
- **RF-7** Nenhum caminho de arquivo (`avatar_path`) é exposto; só a URL.
- **RF-8** Mensagens e erros de validação em pt-BR; erros de domínio no padrão `{error, message}`.

## Contrato de API (todas `auth:sanctum`, sem exigir e-mail verificado)
- `PATCH /api/me` `{name, phone|null, bio|null, timezone}` → 200 `{data: <MeResource>}` · 401 · 422
- `POST /api/me/avatar/upload-url` `{content_type}` → 200 `{url, headers, path, content_type, max_size, expires_in}` · 401 · 422
- `PUT /api/me/avatar` `{path}` → 200 `{data: <MeResource>}` · 401 · 422 (`path` inválido) · 422 `{error: profile_invalid_avatar_file | profile_avatar_too_large}`
- `DELETE /api/me/avatar` → 200 `{data: <MeResource>}` com `avatar: null` · 401
- `PUT /api/me/password` `{current_password, password, password_confirmation}` → 200 `{message}` · 401 · 422 (`errors.current_password` quando errada)

## Critérios de aceite
- Dados salvos aparecem no `GET /me`; campos protegidos enviados no `PATCH` não mudam nada.
- Fluxo da foto: upload-url → PUT no S3 → confirmar → `GET /me` traz `avatar`; trocar apaga a antiga; remover limpa.
- Path de outro usuário, de outro diretório ou inexistente → 422 e nada muda no banco.
- Troca de senha: com a senha atual correta e regras ok, outros tokens somem e o atual segue válido.
- Sem token → 401 em todas. Suíte completa verde.

## Fora de escopo
`DELETE /api/me` (excluir conta: regra de bloqueios, Stripe e FK sem cascade em `scheduled_posts` ainda a decidir); e-mail alterável; consertar `PATCH /users/{uuid}` ou o `login` que devolve o model cru; limpeza de uploads órfãos.

## Questões em aberto
1. Excluir conta: decidir regra (workspaces com outros membros, assinatura ativa, soft delete) numa spec própria.
