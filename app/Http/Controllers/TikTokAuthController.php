<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TikTokAuthController extends Controller
{
    /**
     * PASSO 1: Redirecionar para o TikTok
     */
    public function redirectToTikTok()
    {
        $query = http_build_query([
            'client_key' => env('TIKTOK_CLIENT_KEY'),
            'scope' => 'user.info.basic,video.upload',
            // Certifique-se de que video.publish está EXATAMENTE assim na string
            // 'scope' => 'user.info.basic,video.upload,video.publish',
            'response_type' => 'code',
            'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
            'state' => 'pandy123',
        ]);

        return redirect('https://www.tiktok.com/v2/auth/authorize/?' . $query);
    }
    /**
     * PASSO 2: Callback e Postagem Automática
     */
    public function handleTikTokCallback(Request $request)
    {
        if ($request->has('error')) {
            return response()->json(['error' => 'Acesso negado'], 401);
        }

        // 1. Obter Access Token
        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => env('TIKTOK_CLIENT_KEY'),
            'client_secret' => env('TIKTOK_CLIENT_SECRET'),
            'code' => $request->get('code'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
        ]);

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            return response()->json(['error' => 'Falha no Token', 'details' => $tokenData], 500);
        }

        // 2. Localizar o vídeo no Storage
        $videoPath = storage_path('app/video.mp4');
        if (!file_exists($videoPath)) {
            return response()->json(['error' => 'Arquivo video.mp4 não encontrado em storage/app/'], 404);
        }
        $videoSize = filesize($videoPath);

        /**
         * 3. INICIALIZAR POSTAGEM (Direct Post v2)
         * Endpoint: /v2/post/publish/video/init/
         */
        $initResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->post('https://open.tiktokapis.com/v2/post/publish/inbox/video/init/', [
                'source_info' => [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => $videoSize,
                    'chunk_size' => $videoSize,
                    'total_chunk_count' => 1
                ]
            ]);

        $initData = $initResponse->json();
        $uploadUrl = $initData['data']['upload_url'] ?? null;

        if (!$uploadUrl) {
            return response()->json(['error' => 'Erro na inicialização', 'details' => $initData], 500);
        }

        /**
         * 4. ENVIO DO BINÁRIO (PUT)
         * Conforme a documentação: Requer Content-Range e Content-Type
         */
        $lastByte = $videoSize - 1;
        $fileResource = fopen($videoPath, 'r');
        $uploadResponse = Http::withHeaders([
            'Content-Type' => 'video/mp4',
            'Content-Length' => $videoSize,
            'Content-Range' => "bytes 0-{$lastByte}/{$videoSize}", // Essencial para v2
        ])->send('PUT', $uploadUrl, [
                    'body' => $fileResource,
                ]);
        fclose($fileResource);
        return response()->json([
            'status' => 'sucesso',
            'publish_id' => $initData['data']['publish_id'] ?? null,
            'tiktok_response' => $uploadResponse->status(),
            'refresh_token' => $tokenData['refresh_token'] ?? null
        ]);
    }
}