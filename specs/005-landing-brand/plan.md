# Plano — 005 Tema padrão da landing

## Decisões
- **Armazenamento:** tabela `app_settings` (`key` único, `value` string, timestamps) e model `AppSetting` com a chave `landing.brand`. Serve também para as próximas configurações globais sem nova migration. *Descartado:* arquivo de config/`.env` (exige deploy, que é justamente o problema); tabela de uma linha só para a landing (rígida, uma migration por configuração nova).
- **Valores aceitos:** enum `App\Enums\LandingBrand` (`pop`, `violeta`, `coral`, `bambu`) com `default()` = `pop` e `tryFrom` na leitura (valor inválido salvo cai no padrão). *Descartado:* validar contra lista em config (duas fontes da verdade).
- **Leitura pública sem cache.** Uma consulta por chave, protegida por `throttle:45,1`. Cache só se aparecer necessidade.
- **Escrita** em `PUT` (substitui o valor), idempotente.

## Arquivos
- `database/migrations/*_create_app_settings_table.php` (SQL compatível com PostgreSQL e SQLite).
- `app/Models/AppSetting.php` (`$fillable = ['key','value']`; sem `uuid`: não é recurso exposto).
- `app/Enums/LandingBrand.php`.
- `app/Services/LandingSettingsService.php` (`brand(): LandingBrand`, `updateBrand(LandingBrand)`).
- `app/Http/Controllers/Api/LandingSettingsController.php` (`show`) e `Admin/LandingSettingsController.php` (`update`).
- `app/Http/Requests/Admin/UpdateLandingSettingsRequest.php` (`brand` => `['required', Rule::enum(LandingBrand::class)]`).
- `app/Http/Resources/LandingSettingsResource.php` (só `brand`).
- `routes/api.php`: `GET landing-settings` perto de `plans`; `PUT admin/landing-settings` dentro do grupo `admin`.

## Efeitos colaterais
Nenhum (sem evento, job ou notificação).

## Testes (`tests/Feature/LandingSettings/LandingSettingsTest.php`)
Padrão; valor salvo; valor inválido no banco; PUT happy path e idempotência; 401; 403 para usuário comum **e** para dono de workspace com permissões; 422 (ausente, inválido, tipo errado); resposta pública só com `brand`.

## Riscos
- Enum da API e registro de temas do front podem divergir → mitigado pelo aviso na spec e pelo fallback do front (valor desconhecido usa o tema padrão do front).
