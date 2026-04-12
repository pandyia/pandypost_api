<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;

class YouTubeAuthController extends Controller
{
    /**
     * Redireciona para o Google.
     */
    public function redirectToGoogle(): JsonResponse|RedirectResponse
    {
        // Garante que o usuário está logado no seu sistema antes de ir ao Google
        if (!Auth::check()) {
            return response()->json(['error' => 'Você precisa estar logado para conectar uma conta.'], 401);
        }

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->scopes([
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly'
        ])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent'
            ])
            ->redirect();
    }

    /**
     * Callback do Google.
     */
    public function handleGoogleCallback(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Sessão expirada ou usuário não autenticado.'], 401);
        }

        try {
            // O Socialite captura o 'code' da URL e troca pelo Access Token automaticamente
            $googleUser = Socialite::driver('google')->user();

            // Preparação dos dados usando o objeto retornado pelo Socialite
            $dataToUpdate = [
                'access_token' => $googleUser->token,
                'expires_at' => now()->addSeconds($googleUser->expiresIn),
                'platform_id' => $googleUser->getId(),
                'nickname' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
            ];

            // Só atualizamos o refresh_token se o Google nos enviar um novo
            if (!empty($googleUser->refreshToken)) {
                $dataToUpdate['refresh_token'] = $googleUser->refreshToken;
            }

            // UpdateOrCreate rigoroso
            $socialAccount = SocialAccount::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'platform' => 'youtube',
                ],
                $dataToUpdate
            );

            return response()->json([
                'status' => 'sucesso',
                'message' => 'Conta YouTube vinculada com sucesso via Socialite!',
                'has_refresh_token' => !empty($socialAccount->refresh_token)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Falha ao autenticar com Google',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}