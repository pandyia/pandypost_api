<?php

use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// Spec 018 RF-7 e RF-8: o que o front envia vs. o que as redes aceitam, e o fuso do scheduled_at.

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
    Storage::fake('s3');
    $this->user = createUserWithPermissions(['posts.create', 'posts.view']);
    $this->ws = $this->user->currentAccess->workspace->uuid;
});

function schedFile(string $dir, string $name): string
{
    $path = 'workspaces/'.test()->ws."/{$dir}/{$name}";
    Storage::disk('s3')->put($path, 'conteudo');

    return $path;
}

function schedPost(string $platform, array $body): TestResponse
{
    $account = connectedSocialAccount(test()->user, $platform, $platform === 'tiktok' ? ['scopes' => 'video.publish'] : []);

    return test()->withToken(test()->user->test_token)->postJson('/api/scheduled-posts', array_merge([
        'social_account_uuids' => [$account->uuid],
        'title' => 'Título',
        'caption' => 'Legenda',
    ], $body));
}

describe('scheduled_at e fuso', function () {

    it('converte o instante recebido para o fuso da aplicação e atrasa o job até esse instante', function (string $input, int $hoursAhead) {
        $expected = Carbon::parse($input);

        schedPost('youtube', ['media_storage_path' => schedFile('videos', 'a.mp4'), 'scheduled_at' => $input])->assertCreated();

        $post = ScheduledPost::withoutGlobalScopes()->sole();
        expect($post->scheduled_at->timestamp)->toBe($expected->timestamp);
        Queue::assertPushed(PublishPostJob::class, fn ($job) => Carbon::instance($job->delay)->timestamp === $expected->timestamp);
    })->with([
        'ISO com Z (JS toISOString)' => [fn () => now()->addHours(3)->utc()->format('Y-m-d\TH:i:s.v\Z'), 3],
        'offset -03:00' => [fn () => now()->addHours(3)->setTimezone('-03:00')->format('Y-m-d\TH:i:sP'), 3],
        'offset +09:00' => [fn () => now()->addHours(3)->setTimezone('+09:00')->format('Y-m-d\TH:i:sP'), 3],
        'sem offset vale no fuso da aplicação' => [fn () => now()->addHours(3)->format('Y-m-d H:i:s'), 3],
    ]);

    it('o reenvio com data também converte o fuso', function () {
        $account = connectedSocialAccount($this->user, 'youtube');
        $post = ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $this->user->id, 'social_account_id' => $account->id, 'platform' => 'youtube', 'title' => 'x',
            'media_path' => schedFile('videos', 'r.mp4'), 'status' => 'failed', 'failed_at' => now(),
        ]);
        $input = now()->addHours(5)->utc()->format('Y-m-d\TH:i:s\Z');

        $this->withToken($this->user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry", ['scheduled_at' => $input])->assertOk();

        expect($post->fresh()->scheduled_at->timestamp)->toBe(Carbon::parse($input)->timestamp);
    });

});

describe('validações por rede (422, nada é criado)', function () {

    it('rejeita o que a rede não aceita', function (string $platform, string $dir, string $file, array $extra, string $field) {
        $response = schedPost($platform, array_merge(['media_storage_path' => schedFile($dir, $file)], $extra));

        $response->assertStatus(422)->assertJsonValidationErrors($field);
        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'YouTube com imagem' => ['youtube', 'images', 'a.jpg', [], 'media_storage_path'],
        'YouTube sem título' => ['youtube', 'videos', 'a.mp4', ['title' => null], 'title'],
        'YouTube com título de 101 caracteres' => ['youtube', 'videos', 'a.mp4', ['title' => str_repeat('a', 101)], 'title'],
        'YouTube com descrição acima de 5.000 bytes' => ['youtube', 'videos', 'a.mp4', ['caption' => str_repeat('é', 2600)], 'caption'],
        'TikTok com imagem' => ['tiktok', 'images', 'a.png', ['privacy_level' => 'SELF_ONLY'], 'media_storage_path'],
        'Instagram com PNG' => ['instagram', 'images', 'a.png', [], 'media_storage_path'],
        'Instagram com legenda de 2.201 caracteres' => ['instagram', 'videos', 'a.mp4', ['caption' => str_repeat('a', 2201)], 'caption'],
    ]);

    it('aceita o que a rede aceita', function (string $platform, string $dir, string $file, array $extra) {
        schedPost($platform, array_merge(['media_storage_path' => schedFile($dir, $file)], $extra))->assertCreated();

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(1);
        Queue::assertPushed(PublishPostJob::class, 1);
    })->with([
        'YouTube vídeo' => ['youtube', 'videos', 'a.mp4', []],
        'YouTube vídeo mov com thumbnail opcional' => ['youtube', 'videos', 'a.mov', []],
        'TikTok vídeo' => ['tiktok', 'videos', 'a.mp4', ['privacy_level' => 'SELF_ONLY']],
        'Instagram vídeo' => ['instagram', 'videos', 'a.mp4', []],
        'Instagram imagem JPEG' => ['instagram', 'images', 'a.jpg', []],
        'Instagram sem legenda' => ['instagram', 'images', 'b.jpg', ['caption' => null]],
        'Instagram com 2.200 caracteres' => ['instagram', 'videos', 'c.mp4', ['caption' => str_repeat('a', 2200)]],
    ]);

    it('várias contas: uma rede inválida desfaz tudo (nenhum post, nenhum job)', function () {
        $yt = connectedSocialAccount($this->user, 'youtube');
        $ig = connectedSocialAccount($this->user, 'instagram');

        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => schedFile('images', 'a.png'),
            'social_account_uuids' => [$ig->uuid, $yt->uuid],
            'title' => 'x',
        ])->assertStatus(422);

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

});
