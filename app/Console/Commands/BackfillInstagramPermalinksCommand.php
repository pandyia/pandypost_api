<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Models\ScheduledPost;
use App\Services\InstagramService;
use Illuminate\Console\Command;
use Throwable;

class BackfillInstagramPermalinksCommand extends Command
{
    protected $signature = 'instagram:backfill-permalinks {--dry-run : Só lista os posts, sem consultar a Graph API nem gravar}';

    protected $description = 'Grava o permalink dos posts do Instagram publicados antes de a publicação passar a guardá-lo';

    public function handle(InstagramService $instagram): void
    {
        $isDryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $failed = 0;

        // Sem workspace corrente no console: percorre todos os tenants de propósito.
        $query = ScheduledPost::withoutGlobalScopes()
            ->with(['socialAccount' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('platform', Platform::INSTAGRAM->value)
            ->where('status', ScheduledPostStatus::PUBLISHED->value)
            ->whereNotNull('platform_post_id')
            ->whereNull('platform_post_url');

        $total = $query->count();

        if ($total === 0) {
            $this->info('Nenhum post do Instagram sem link.');

            return;
        }

        if ($isDryRun) {
            $query->each(fn (ScheduledPost $post) => $this->line("Post {$post->uuid} (mídia {$post->platform_post_id})"));
            $this->info("{$total} post(s) seriam atualizados.");

            return;
        }

        // Token por conta: evita renovar o mesmo token a cada post.
        $tokens = [];

        $query->chunkById(100, function ($posts) use ($instagram, &$tokens, &$updated, &$failed) {
            foreach ($posts as $post) {
                $account = $post->socialAccount;
                if (! $account) {
                    $this->warn("Post {$post->uuid}: conta social não encontrada");
                    $failed++;

                    continue;
                }

                try {
                    $tokens[$account->id] ??= $account->getValidToken();
                    $permalink = $instagram->fetchPermalink($post->platform_post_id, $tokens[$account->id]);

                    $post->update(['platform_post_url' => $permalink]);
                    $updated++;
                } catch (Throwable $e) {
                    // Um post sem link não pode interromper os demais.
                    $this->warn("Post {$post->uuid}: {$e->getMessage()}");
                    $failed++;
                }
            }
        });

        $this->info("{$updated} post(s) atualizados, {$failed} com falha.");
    }
}
