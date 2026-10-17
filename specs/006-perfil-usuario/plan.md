# Plano — 006 Perfil do usuário

## Decisões
- **Foto por URL pré-assinada** (padrão do sistema de arquivos), path `users/{uuid}/avatars/{uuid}.{ext}` (o avatar é do usuário, não de um workspace). *Descartado:* multipart pela API (contraria o padrão e passa o arquivo pelo PHP).
- **Confirmação em duas etapas** (`upload-url` → PUT → `PUT /me/avatar`), validando de verdade tipo e tamanho depois do upload (`size`/`mimeType`), porque o PUT pré-assinado não limita nada. Inválido: apaga e erra.
- **URL assinada longa** (`presigned_avatar_ttl`, padrão 6 dias) gerada a cada `GET /me`; arquivo continua privado. *Descartado:* URL pública (exigiria mudança de infra do bucket). Sem entrada no `.env.example` (config com padrão).
- **Erros de domínio** com `ProfileError` + `ProfileException` (padrão `AnalyticsError`).
- **`avatar_path` em `$hidden`** do model: `login`/`signup` devolvem o model cru e não podem vazar o caminho.
- **`StorageService`**: método novo `generateUserUploadUrl` + `pathBelongsToUser`; o PUT pré-assinado vira um helper privado compartilhado com `generateUploadUrl` (mesma saída, usado pelo wizard).

## Arquivos
- migration `add_profile_fields_to_users_table`; `app/Models/User.php`
- `app/Services/Storage/StorageService.php`; `config/services.php`
- `app/Rules/AvatarPathRule.php`
- `app/Enums/Exceptions/ProfileError.php`, `app/Exceptions/ProfileException.php`
- `app/Services/ProfileService.php`
- `app/Http/Controllers/Api/ProfileController.php`
- `app/Http/Requests/Profile/{UpdateProfile,AvatarUploadUrl,SetAvatar,ChangePassword}Request.php`
- `app/Http/Resources/MeResource.php`; docblock de `AuthController::me`
- `routes/api.php`
- `tests/Feature/Profile/*`

## Efeitos colaterais
Arquivos no S3 (apagados ao trocar/remover/inválido); tokens revogados na troca de senha; auditoria (`User` é `Auditable`).

## Riscos
- `email_verified_at` muda de `d/m/Y H:i:s` para ISO 8601 (o front só testa se existe).
- Upload sem confirmação deixa objeto órfão no S3 (débito existente, sem limpeza agendada).
