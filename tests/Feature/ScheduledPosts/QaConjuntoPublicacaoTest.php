<?php

use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// QA do conjunto 011/014-018: fluxos que o teste manual vai percorrer (agendar em várias redes com o
// mesmo arquivo, cancelar um deles, desconectar a conta) com o StorageService REAL sobre o S3 fake.

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('s3');
    $this->user = createUserWithPermissions(['posts.create', 'posts.view', 'posts.delete', 'social_accounts.disconnect', 'social_accounts.view']);
    $this->ws = $this->user->currentAccess->workspace->uuid;
});

function qaVideo(string $name = 'video.mp4'): string
{
    $path = 'workspaces/'.test()->ws."/videos/{$name}";
    Storage::disk('s3')->put($path, 'conteudo');

    return $path;
}

describe('agendar o mesmo arquivo em várias redes', function () {

    it('cria um post por rede, cada um na fila da sua rede, com o payload da rede e sem o arquivo ser apagado', function () {
        Queue::fake();
        $media = qaVideo();
        $yt = connectedSocialAccount($this->user, 'youtube');
        $ig = connectedSocialAccount($this->user, 'instagram');
        $tt = connectedSocialAccount($this->user, 'tiktok', ['scopes' => 'video.upload,video.publish']);

        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $media,
            'social_account_uuids' => [$yt->uuid, $ig->uuid, $tt->uuid],
            'title' => 'Título',
            'caption' => 'Legenda',
            'youtube_privacy_status' => 'private',
            'privacy_level' => 'SELF_ONLY',
        ])->assertCreated()->assertJsonCount(3, 'data');

        $posts = ScheduledPost::withoutGlobalScopes()->get()->keyBy(fn ($p) => $p->platform->value);

        expect($posts)->toHaveCount(3)
            ->and($posts['youtube']->payload['youtube_privacy_status'])->toBe('private')
            ->and($posts['tiktok']->payload['privacy_level'])->toBe('SELF_ONLY')
            ->and($posts->every(fn ($p) => $p->media_path === $media && $p->status === 'pending'))->toBeTrue();

        foreach (['youtube', 'instagram', 'tiktok'] as $queue) {
            Queue::assertPushedOn($queue, PublishPostJob::class);
        }
        expect(Storage::disk('s3')->exists($media))->toBeTrue();
    });
});

describe('cancelar um post que divide o arquivo com outros', function () {

    function qaPostsForSameMedia(string $media): array
    {
        $yt = connectedSocialAccount(test()->user, 'youtube');
        $ig = connectedSocialAccount(test()->user, 'instagram');

        return array_map(fn ($account) => ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => test()->user->id,
            'social_account_id' => $account->id,
            'platform' => $account->platform,
            'title' => 'Título',
            'media_path' => $media,
            'status' => 'pending',
            'scheduled_at' => now()->addDay(),
        ]), [$yt, $ig]);
    }

    it('cancelar o post do YouTube NÃO apaga o arquivo que o post do Instagram ainda vai publicar', function () {
        $media = qaVideo();
        [$youtube, $instagram] = qaPostsForSameMedia($media);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$youtube->uuid}")->assertOk();

        expect($youtube->fresh()->status)->toBe('cancelled')
            ->and($instagram->fresh()->status)->toBe('pending')
            ->and(Storage::disk('s3')->exists($media))->toBeTrue('o Instagram ainda precisa do arquivo');
    });

    it('cancelar o último post que usa o arquivo apaga o arquivo', function () {
        $media = qaVideo();
        [$youtube] = qaPostsForSameMedia($media);
        ScheduledPost::withoutGlobalScopes()->where('platform', 'instagram')->update(['status' => 'cancelled']);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$youtube->uuid}")->assertOk();

        expect(Storage::disk('s3')->exists($media))->toBeFalse();
    });
});

describe('desconectar a conta', function () {

    it('cancela só os pendentes; os publicados seguem como estão', function () {
        Http::fake();
        $account = connectedSocialAccount($this->user, 'youtube');
        $make = fn (string $status) => ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $this->user->id,
            'social_account_id' => $account->id,
            'platform' => 'youtube',
            'title' => $status,
            'media_path' => 'workspaces/'.$this->ws.'/videos/x.mp4',
            'status' => $status,
        ]);
        $pending = $make('pending');
        $published = $make('published');

        $this->withToken($this->user->test_token)->deleteJson("/api/social-accounts/{$account->uuid}")->assertOk();

        expect($pending->fresh()->status)->toBe('cancelled')
            ->and($published->fresh()->status)->toBe('published');

        // Pergunta aberta (relatório QA): a conta é apagada (FK nullOnDelete) e o escopo de workspace do post
        // passa pela conta, então o histórico some de GET /scheduled-posts. A spec não diz se isso é desejado.
    });
});
