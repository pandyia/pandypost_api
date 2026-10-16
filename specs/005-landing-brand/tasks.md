# Tarefas — 005 Tema padrão da landing

- [x] Migration `app_settings` — teste: `migrate` em SQLite (suíte) sobe sem erro
- [x] Model `AppSetting` — teste: grava e lê `landing.brand`
- [x] Enum `LandingBrand` com `default()` — teste: `tryFrom('x')` nulo, padrão `pop`
- [x] `LandingSettingsService` — teste: padrão sem registro; padrão com valor inválido; grava e relê
- [x] `GET /api/landing-settings` + Resource — teste: 200 padrão, 200 salvo, só `brand` na resposta
- [x] `PUT /api/admin/landing-settings` + FormRequest — teste: 200, idempotência, 401, 403 (usuário comum e dono de workspace), 422
- [x] Pint nos arquivos alterados e suíte completa — verificação: `php artisan test` verde

## Divergências
- Um único `LandingSettingsController` por rota, como no plano (público em `Api/`, escrita em `Api/Admin/`). Sem divergência real.
- `Status` da spec: aprovada pelo usuário em 2026-10-16 (decisão: sem auditoria, sem cache).
