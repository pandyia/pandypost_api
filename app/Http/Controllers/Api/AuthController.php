<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\MeResource;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    public function signup(RegisterRequest $request)
    {
        $result = $this->authService->signup($request->validated());

        return response()->json($result, 201);
    }

    public function resendVerification(Request $request)
    {
        $this->authService->resendVerification($request->user());

        return response()->json(['message' => 'Código reenviado com sucesso.'], 200);
    }

    public function confirmEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $this->authService->confirmEmail($request->input('token'));

        return response()->json(['message' => 'Email verificado com sucesso.'], 200);
    }
    public function passwordReset(ResetPasswordRequest $request)
    {
        $this->authService->resetPassword($request->validated());

        return response()->json(['message' => 'Senha redefinida com sucesso.'], 200);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $this->authService->forgotPassword($request->validated()['email']);

        return response()->json(['message' => 'Se o email existir, um link de recuperação será enviado.'], 200);
    }

    public function login(LoginRequest $request)
    {
        $data = $this->authService->login($request->all());

        return response()->json($data, 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return new MeResource($request->user());
    }
}
