<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\MeResource;
use App\Services\AuthService;
use Illuminate\Http\Request;

/**
 * @group Autenticação
 *
 * Cadastro, login, confirmação de email e recuperação de senha.
 *
 * Fluxo: `signup` → usuário recebe um código por email → `confirm-email` → `login`.
 */
class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Cadastrar usuário
     *
     * Cria o usuário e o workspace pessoal dele, e envia o código de confirmação por email.
     * O token devolvido já funciona, mas só para `/me`, `/logout` e reenvio do código até o email ser confirmado.
     *
     * Limite: 3 requisições por minuto.
     *
     * @unauthenticated
     *
     * @bodyParam name string required Nome (máx. 255). Example: Ana Souza
     * @bodyParam email string required Email (máx. 255), ainda não cadastrado. Example: ana@exemplo.com
     * @bodyParam password string required Senha, mín. 8 caracteres. Example: senha-forte-123
     * @bodyParam password_confirmation string required Repetição da senha. Example: senha-forte-123
     *
     * @response 201 {
     *   "user": {
     *   "uuid": "2bd8fa57-724f-4cfb-ae33-875afedee0e6",
     *   "name": "Ana Souza",
     *   "email": "ana@exemplo.com",
     *   "email_verified_at": null,
     *   "is_active": true,
     *   "remember_me": false,
     *   "is_super_admin": false,
     *   "access_id": 2,
     *   "created_at": "2026-10-10T16:52:25.000000Z",
     *   "updated_at": "2026-10-15T21:39:45.000000Z"
     * },
     *   "token": "1|Xk2...",
     *   "status": "unverified"
     * }
     * @response 422 {"message": "Este e-mail já está cadastrado em nossa plataforma.", "errors": {"email": ["Este e-mail já está cadastrado em nossa plataforma."]}}
     */
    public function signup(RegisterRequest $request)
    {
        $result = $this->authService->signup($request->validated());

        return response()->json($result, 201);
    }

    /**
     * Reenviar código de confirmação
     *
     * Envia de novo o código de confirmação para o email do usuário logado. Funciona sem email verificado.
     *
     * Limite: 3 requisições por minuto.
     *
     * @response 200 {"message": "Código reenviado com sucesso."}
     * @response 409 {"error": "already_verified", "message": "Email já verificado."}
     */
    public function resendVerification(Request $request)
    {
        $this->authService->resendVerification($request->user());

        return response()->json(['message' => 'Código reenviado com sucesso.'], 200);
    }

    /**
     * Confirmar email
     *
     * Valida o código recebido por email. Depois disso o usuário pode fazer login.
     *
     * Limite: 10 requisições por minuto.
     *
     * @unauthenticated
     *
     * @bodyParam token string required Código recebido por email. Example: aB3dE5gH7j
     *
     * @response 200 {"message": "Email verificado com sucesso."}
     * @response 400 scenario="código inválido" {"error": "invalid_token", "message": "Token inválido."}
     * @response 400 scenario="código expirado" {"error": "expired_token", "message": "Token expirado."}
     */
    public function confirmEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $this->authService->confirmEmail($request->input('token'));

        return response()->json(['message' => 'Email verificado com sucesso.'], 200);
    }

    /**
     * Redefinir senha
     *
     * Troca a senha usando o token recebido no email de recuperação.
     *
     * Limite: 3 requisições por minuto.
     *
     * @unauthenticated
     *
     * @bodyParam token string required Token recebido no email de recuperação. Example: Zx8Yw7Vu6T
     * @bodyParam email string required Email da conta. Example: ana@exemplo.com
     * @bodyParam password string required Nova senha, mín. 8 caracteres. Example: nova-senha-123
     * @bodyParam password_confirmation string required Repetição da nova senha. Example: nova-senha-123
     *
     * @response 200 {"message": "Senha redefinida com sucesso."}
     * @response 400 {"error": "invalid_token", "message": "Token inválido."}
     */
    public function passwordReset(ResetPasswordRequest $request)
    {
        $this->authService->resetPassword($request->validated());

        return response()->json(['message' => 'Senha redefinida com sucesso.'], 200);
    }

    /**
     * Esqueci minha senha
     *
     * Envia o link de recuperação. A resposta é sempre a mesma, exista ou não o email.
     *
     * Limite: 3 requisições por minuto.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Email da conta. Example: ana@exemplo.com
     *
     * @response 200 {"message": "Se o email existir, um link de recuperação será enviado."}
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $this->authService->forgotPassword($request->validated()['email']);

        return response()->json(['message' => 'Se o email existir, um link de recuperação será enviado.'], 200);
    }

    /**
     * Login
     *
     * Devolve o token de acesso e o workspace corrente.
     * Com `remember_me: true` o token dura mais.
     *
     * Limite: 55 requisições por minuto.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Email. Example: ana@exemplo.com
     * @bodyParam password string required Senha. Example: senha-forte-123
     * @bodyParam remember_me boolean Manter conectado (token de duração maior). Example: false
     *
     * @response 200 {
     *   "user": {
     *   "uuid": "2bd8fa57-724f-4cfb-ae33-875afedee0e6",
     *   "name": "Ana Souza",
     *   "email": "ana@exemplo.com",
     *   "email_verified_at": "2026-10-10T16:52:25.000000Z",
     *   "is_active": true,
     *   "remember_me": false,
     *   "is_super_admin": false,
     *   "access_id": 2,
     *   "created_at": "2026-10-10T16:52:25.000000Z",
     *   "updated_at": "2026-10-15T21:39:45.000000Z"
     * },
     *   "token": "3|pZ9...",
     *   "current_workspace": {
     *     "uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833",
     *     "name": "Workspace da Ana",
     *     "is_personal_team": true,
     *     "stripe_id": null,
     *     "pm_type": null,
     *     "pm_last_four": null,
     *     "trial_ends_at": null,
     *     "created_at": "2026-10-10T16:52:25.000000Z",
     *     "updated_at": "2026-10-10T16:52:25.000000Z"
     *   }
     * }
     * @response 401 scenario="senha errada" {"error": "invalid_credentials", "message": "Credenciais inválidas."}
     * @response 404 scenario="email não cadastrado" {"error": "user_not_found", "message": "Usuário não encontrado."}
     * @response 403 scenario="email não confirmado" {"error": "email_not_verified", "message": "Confirme seu email para continuar!"}
     */
    public function login(LoginRequest $request)
    {
        $data = $this->authService->login($request->all());

        return response()->json($data, 200);
    }

    /**
     * Logout
     *
     * Revoga o token usado na requisição.
     *
     * @response 204 scenario="sucesso" ""
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Usuário logado
     *
     * Dados do usuário, workspace corrente, perfil e permissões nesse workspace.
     * Use `permissions` para decidir o que mostrar na interface. Super admin recebe todas as permissões.
     * Funciona sem email verificado: confira `email_verified_at` para saber se precisa confirmar.
     *
     * @response 200 {
     *   "data": {
     *     "id": 2,
     *     "name": "Ana Souza",
     *     "email": "ana@exemplo.com",
     *     "is_active": true,
     *     "email_verified_at": "10/10/2026 16:52:25",
     *     "is_super_admin": false,
     *     "current_workspace": {"uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833", "name": "Workspace da Ana"},
     *     "role": "Admin",
     *     "permissions": ["users.view", "users.create", "posts.view", "posts.create", "billing.view"]
     *   }
     * }
     *
     * @responseField email_verified_at string|null ISO 8601. `null` = email não confirmado.
     * @responseField avatar string|null URL assinada da foto de perfil (muda a cada chamada), ou `null`.
     * @responseField phone string|null Só dígitos (DDD + número).
     * @responseField bio string|null Até 160 caracteres.
     * @responseField timezone string Fuso horário IANA.
     * @responseField created_at string ISO 8601.
     * @responseField current_workspace object Ausente se o usuário não tiver workspace.
     */
    public function me(Request $request)
    {
        return new MeResource($request->user());
    }
}
