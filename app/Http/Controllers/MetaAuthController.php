<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAuthController extends Controller
{
    /**
     * 1️⃣ Redireciona para o login direto do Instagram
     */
    public function redirect()
    {
        $query = http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => config('services.meta.redirect'),
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_content_publish',
        ]);

        return redirect('https://www.instagram.com/oauth/authorize?' . $query);
    }

    /**
     * 2️⃣ Callback: Troca o código e faz a postagem diretamente
     */
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return response()->json(['error' => $request->error], 400);
        }

        // A. Troca o código pelo Token de Acesso
        $tokenResponse = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.meta.redirect'),
            'code' => $request->code,
        ])->json();

        $accessToken = $tokenResponse['access_token'] ?? null;
        $instagramId = $tokenResponse['user_id'] ?? null;

        if (!$accessToken || !$instagramId) {
            return response()->json(['error' => 'Falha na autenticação', 'debug' => $tokenResponse], 400);
        }

        // B. POSTAGEM (Fluxo de 3 etapas: criar container, aguardar, publicar)

        // Passo 1: Criar o container da mídia
        $containerResponse = Http::post("https://graph.instagram.com/v24.0/{$instagramId}/media", [
            'image_url' => 'https://i.imgur.com/Anez2Qn.png',
            'caption' => 'Postado direto pelo Laravel 🚀 #PandyPost',
            'access_token' => $accessToken,
        ])->json();

        $creationId = $containerResponse['id'] ?? null;

        if (!$creationId) {
            return response()->json(['error' => 'Erro ao criar container', 'details' => $containerResponse], 400);
        }

        Log::info("Container criado: {$creationId}. Aguardando processamento...");

        // Passo 2: Aguardar o container ficar pronto (polling)
        $isReady = $this->waitForContainerReady($creationId, $accessToken);

        if (!$isReady) {
            return response()->json([
                'error' => 'Timeout aguardando processamento',
                'container_id' => $creationId,
                'message' => 'O Instagram demorou muito para processar a imagem. Tente novamente.'
            ], 408);
        }

        // Passo 3: Publicar o container
        $publishResponse = Http::post("https://graph.instagram.com/v24.0/{$instagramId}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $accessToken,
        ])->json();

        if (isset($publishResponse['error'])) {
            return response()->json([
                'error' => 'Erro na publicação',
                'details' => $publishResponse
            ], 400);
        }

        return response()->json([
            'status' => 'Sucesso! 🎉',
            'post_id' => $publishResponse['id'] ?? null,
            'message' => 'Post publicado no Instagram com sucesso!'
        ]);
    }

    /**
     * Aguarda o container de mídia ficar pronto para publicação
     * Faz polling a cada 2 segundos, máximo 30 segundos
     */
    private function waitForContainerReady(string $containerId, string $accessToken, int $maxAttempts = 15): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $statusResponse = Http::get("https://graph.instagram.com/v24.0/{$containerId}", [
                'fields' => 'status_code',
                'access_token' => $accessToken,
            ])->json();

            $status = $statusResponse['status_code'] ?? null;

            Log::info("Container {$containerId} - Status: {$status} (tentativa " . ($i + 1) . ")");

            if ($status === 'FINISHED') {
                return true;
            }

            if ($status === 'ERROR') {
                Log::error("Container falhou", $statusResponse);
                return false;
            }

            // Aguarda 2 segundos antes da próxima verificação
            sleep(2);
        }

        return false;
    }
}