<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Models\ScheduledPost;
use App\Services\Factories\SocialMediaFactory;
use App\Services\InstagramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WarmupPostsCommand extends Command
{
    protected $signature = 'posts:warmup';
    protected $description = 'Prepara containers do Instagram antecipadamente para evitar delay na publicação';

    public function handle(SocialMediaFactory $factory): void
    {
        $posts = ScheduledPost::query()
            ->where('platform', 'instagram')
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [now(), now()->addMinutes(60)])
            ->with(['socialAccount'])
            ->get();

        if ($posts->isEmpty()) {
            return;
        }

        $this->info("Encontrados {$posts->count()} posts para warmup.");

        foreach ($posts as $post) {
            if ($post->hasValidContainer()) {
                continue;
            }

            $account = $post->socialAccount;

            if (!$account) {
                continue;
            }

            try {
                /** @var InstagramService $service */
                $service = $factory->make(Platform::INSTAGRAM);
                $service->prepare($account, $post);
                $this->info("Warmup iniciado para post {$post->id}");
            } catch (\Exception $e) {
                Log::error("Erro no warmup do post {$post->id}: {$e->getMessage()}");
            }
        }
    }
}

