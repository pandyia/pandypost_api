<?php

namespace App\Console\Commands;

use App\Models\ScheduledPost;
use App\Services\Storage\StorageService;
use Illuminate\Console\Command;

class PurgeFailedPostsMediaCommand extends Command
{
    protected $signature = 'posts:purge-failed-media';

    protected $description = 'Apaga a mídia de posts com falha há mais de 5 dias sem reenvio (o post continua na listagem)';

    public function handle(StorageService $storage): void
    {
        $purged = 0;

        ScheduledPost::query()
            ->where('status', 'failed')
            ->whereNull('media_purged_at')
            ->where('failed_at', '<', now()->subDays(ScheduledPost::MEDIA_RETENTION_DAYS))
            ->each(function (ScheduledPost $post) use ($storage, &$purged) {
                // Marca antes: deste ponto o post já não protege o arquivo no deleteIfUnused.
                $post->update(['media_purged_at' => now()]);

                $paths = [$post->media_path];
                if (! empty($post->payload['thumbnail_path'])) {
                    $paths[] = $post->payload['thumbnail_path'];
                }

                $storage->deleteIfUnused($paths, $post->id);
                $purged++;
            });

        if ($purged > 0) {
            $this->info("Mídia limpa de {$purged} post(s) com falha há mais de ".ScheduledPost::MEDIA_RETENTION_DAYS.' dias.');
        }
    }
}
