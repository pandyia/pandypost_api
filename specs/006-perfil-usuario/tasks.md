# Tarefas — 006 Perfil do usuário

- [x] Migration + `User` (fillable, hidden) — teste: colunas existem; `login` não expõe `avatar_path`
- [x] `StorageService` (upload por usuário, `pathBelongsToUser`) + config — teste: path `users/{uuid}/avatars/...`
- [x] `ProfileError`/`ProfileException`, `AvatarPathRule`
- [x] `ProfileService` — teste: troca apaga a antiga; inválido apaga e erra; senha revoga os outros tokens
- [x] FormRequests + `ProfileController` + rotas (throttle) — teste: 401, 422, campos protegidos ignorados
- [x] `MeResource` com campos novos (ISO 8601) — teste: `GET /me`
- [x] Docblocks Scribe
- [x] Pint nos arquivos alterados e suíte completa — verificação: `php artisan test` verde

## Divergências
- Nomes finais no `StorageService`: `generateAvatarUploadUrl` e `pathBelongsToUserAvatars` (o plano dizia `generateUserUploadUrl`/`pathBelongsToUser`). O PUT pré-assinado virou o helper privado `presignedPut`, compartilhado com `generateUploadUrl` (saída idêntica).
- Pint reformatou linhas antigas de `User.php`, `MeResource.php` e `StorageService.php` (estilo do Laravel Pint, como o CLAUDE.md manda rodar): ruído de diff, sem mudança de comportamento.
- Verificado no navegador contra a API local: dados, troca de senha (sessão atual mantida) e o fluxo da foto até a confirmação, com o PUT ao bucket externo interceptado. Upload real ao `play.min.io` não foi feito (depende de autorização).
