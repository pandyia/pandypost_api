<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\AvatarUploadUrlRequest;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\SetAvatarRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\MeResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Meu perfil
 *
 * Dados, foto e senha do **próprio** usuário autenticado. Não exigem e-mail verificado.
 *
 * **Foto de perfil (3 passos, mesmo padrão dos uploads de post):**
 * 1. `POST /me/avatar/upload-url` com o `content_type` → recebe `url`, `headers` e `path`.
 * 2. `PUT` do arquivo direto na `url` (envie os `headers` e o mesmo `Content-Type`; sem token da API).
 * 3. `PUT /me/avatar` com o `path` → a API confere tipo e tamanho (até 5 MB), troca a foto e apaga a antiga.
 *
 * O campo `avatar` do usuário é uma URL assinada que **muda a cada `GET /me`** (válida por ~6 dias).
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $service,
    ) {}

    /**
     * Atualizar dados pessoais
     *
     * O e-mail não é alterável por aqui. Envie `phone` e `bio` como `null` para apagá-los.
     * O telefone aceita máscara; é gravado só com dígitos (DDD + número).
     *
     * @bodyParam name string required Nome completo (3 a 80 caracteres). Example: Maria Souza
     * @bodyParam phone string Telefone com DDD, 10 ou 11 dígitos, ou `null`. Example: 11912345678
     * @bodyParam bio string Até 160 caracteres, ou `null`. Example: Crio conteúdo sobre receitas.
     * @bodyParam timezone string required Fuso horário IANA. Example: America/Sao_Paulo
     *
     * @response 200 {"data": {"id": 12, "name": "Maria Souza", "email": "maria@exemplo.com", "avatar": null, "phone": "11912345678", "bio": "Crio conteúdo sobre receitas.", "timezone": "America/Sao_Paulo", "created_at": "2026-03-02T14:20:00-03:00", "email_verified_at": "2026-03-02T14:25:10-03:00", "is_active": true, "is_super_admin": false, "permissions": []}}
     * @response 422 {"message": "Esse fuso horário não é válido.", "errors": {"timezone": ["Esse fuso horário não é válido."]}}
     */
    public function update(): MeResource
    {
        $data = app(UpdateProfileRequest::class)->validated();

        return new MeResource($this->service->update(request()->user(), $data));
    }

    /**
     * Pedir URL de upload da foto
     *
     * Devolve uma URL pré-assinada de PUT. Depois do PUT, confirme com `PUT /me/avatar`.
     *
     * @bodyParam content_type string required `image/jpeg`, `image/png` ou `image/webp`. Example: image/jpeg
     *
     * @response 200 {"url": "https://storage.exemplo.com/pandypost/users/5f0c.../avatars/a1b2....jpg?X-Amz-Signature=...", "headers": {}, "path": "users/5f0c.../avatars/a1b2....jpg", "content_type": "image/jpeg", "max_size": 5242880, "expires_in": 86400}
     * @response 422 {"message": "Use uma imagem JPG, PNG ou WebP.", "errors": {"content_type": ["Use uma imagem JPG, PNG ou WebP."]}}
     */
    public function avatarUploadUrl(): JsonResponse
    {
        $data = app(AvatarUploadUrlRequest::class)->validated();

        return response()->json($this->service->generateAvatarUploadUrl(request()->user(), $data['content_type']));
    }

    /**
     * Confirmar a foto enviada
     *
     * Confere que o arquivo é do próprio usuário, existe, é imagem JPG/PNG/WebP de até 5 MB, troca a foto
     * e apaga a anterior. Arquivo inválido é apagado do storage.
     *
     * @bodyParam path string required `path` recebido em `POST /me/avatar/upload-url`. Example: users/5f0c.../avatars/a1b2....jpg
     *
     * @response 200 {"data": {"id": 12, "name": "Maria Souza", "avatar": "https://storage.exemplo.com/...assinada...", "email": "maria@exemplo.com"}}
     * @response 422 {"error": "profile_avatar_too_large", "message": "A foto é maior que o limite de 5 MB. Escolha uma imagem menor."}
     */
    public function setAvatar(): MeResource
    {
        $data = app(SetAvatarRequest::class)->validated();

        return new MeResource($this->service->setAvatar(request()->user(), $data['path']));
    }

    /**
     * Remover a foto
     *
     * Apaga o arquivo e limpa o campo. Sem foto, responde 200 do mesmo jeito.
     *
     * @response 200 {"data": {"id": 12, "name": "Maria Souza", "avatar": null, "email": "maria@exemplo.com"}}
     */
    public function removeAvatar(Request $request): MeResource
    {
        return new MeResource($this->service->removeAvatar($request->user()));
    }

    /**
     * Trocar a senha
     *
     * Exige a senha atual. A nova precisa de 8+ caracteres, 1 maiúscula e 1 número, e ser diferente da atual.
     * As **outras** sessões (tokens) são encerradas; a sessão atual continua válida.
     *
     * Limite: 5 requisições por minuto.
     *
     * @bodyParam current_password string required Senha atual. Example: SenhaAntiga1
     * @bodyParam password string required Nova senha. Example: NovaSenha123
     * @bodyParam password_confirmation string required Repetir a nova senha. Example: NovaSenha123
     *
     * @response 200 {"message": "Senha atualizada com sucesso."}
     * @response 422 {"message": "A senha atual está incorreta.", "errors": {"current_password": ["A senha atual está incorreta."]}}
     */
    public function changePassword(): JsonResponse
    {
        $data = app(ChangePasswordRequest::class)->validated();

        $this->service->changePassword(request()->user(), $data['password']);

        return response()->json(['message' => 'Senha atualizada com sucesso.']);
    }
}
