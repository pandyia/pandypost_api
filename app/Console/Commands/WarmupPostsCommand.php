<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Models\ScheduledPost;
use App\Services\InstagramService;
use Illuminate\Console\Command;

// Cria antes do horário os containers dos posts do Instagram que saem na próxima hora.
class WarmupPostsCommand extends Command
{
    protected $signature = 'posts:warmup';

    protected $description = 'Prepara containers do Instagram antecipadamente para evitar delay na publicação';

    private const WINDOW_MINUTES = 60;

    public function handle(InstagramService $instagram): void
    {
        ScheduledPost::query()
            ->where('platform', Platform::INSTAGRAM->value)
            ->where('status', ScheduledPostStatus::PENDING->value)
            ->whereBetween('scheduled_at', [now(), now()->addMinutes(self::WINDOW_MINUTES)])
            ->whereHas('socialAccount')
            ->with('socialAccount')
            ->get()
            ->reject(fn (ScheduledPost $post) => $post->hasValidContainer())
            ->each(function (ScheduledPost $post) use ($instagram) {
                $instagram->prepare($post->socialAccount, $post);
                $this->info("Warmup iniciado para post {$post->id}");
            });
    }
}
