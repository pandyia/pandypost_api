<?php

namespace App\Services;

use App\Contracts\SocialMediaServiceInterface;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

class TikTokService implements SocialMediaServiceInterface
{
    public function upload(SocialAccount $account, ScheduledPost $post): void
    {
        Log::info("Iniciando upload para TikTok. Post ID: {$post->id}");
        
        // TODO: Implementar a Graph API do TikTok futuramente.
        // O arquivo já está salvo no seu Storage local/S3 devido a Estratégia B Universal.
        // Basta enviar a url ($post->media_path) para a API, e deletar no final do processo!
        
        // Simulando sucesso imediato para desenvolvimento
        $post->update([
            'status' => 'published',
            'platform_post_id' => 'tiktok_stub_' . uniqid(),
            'published_at' => now(),
        ]);
        
        Log::info("Post {$post->id} processado com sucesso no TikTok (Stub)!");
        
        // Limpeza simulada do seu disco local/nuvem
        \Illuminate\Support\Facades\Storage::delete($post->media_path);
        
        $payload = $post->payload ?? [];
        if (!empty($payload['thumbnail_path'])) {
            \Illuminate\Support\Facades\Storage::delete($payload['thumbnail_path']);
        }
    }
}
