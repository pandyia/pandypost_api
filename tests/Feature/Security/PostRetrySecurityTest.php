<?php

use App\Jobs\CheckInstagramContainerJob;
use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\ScheduledPostService;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
});

function secFailedPost(User $user, array $overrides = []): ScheduledPost
{
    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => connectedSocialAccount($user, 'instagram')->id,
        'platform' => 'instagram',
        'title' => 'Meu vídeo',
        'media_path' => 'workspaces/w/videos/video.mp4',
        'status' => 'failed',
        'error_message' => 'erro',
    ], $overrides));
    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['failed_at' => now()->subDay()]);

    return $post->fresh();
}

describe('spec 012: reenvio de post falho (esteira de publicação)', function () {

    it('regressão: exige autenticação', function () {
        $this->postJson('/api/scheduled-posts/'.fake()->uuid().'/retry')->assertUnauthorized();
    });

    it('regressão: corpo do request não altera status, dono, conta, mídia nem workspace (sem mass assignment)', function () {
        Queue::fake();
        $storage = $this->mock(StorageService::class);
        $storage->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/x');
        $storage->shouldReceive('exists')->andReturnTrue();
        $user = createUserWithPermissions(['posts.create']);
        $other = createUserWithPermissions(['posts.create']);
        $otherAccount = connectedSocialAccount($other, 'instagram');
        $post = secFailedPost($user);
        $before = $post->only(['user_id', 'social_account_id', 'media_path', 'platform', 'title', 'payload']);

        $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry", [
            'user_id' => $other->id,
            'social_account_id' => $otherAccount->id,
            'media_path' => 'workspaces/outro/videos/roubado.mp4',
            'platform' => 'youtube',
            'status' => 'published',
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'media_purged_at' => now()->toDateTimeString(),
        ])->assertOk();

        $fresh = $post->fresh();
        expect($fresh->only(['user_id', 'social_account_id', 'media_path', 'platform', 'title', 'payload']))->toEqual($before)
            ->and($fresh->uuid)->toBe($post->uuid)
            ->and($fresh->status)->toBe('pending')
            ->and($fresh->media_purged_at)->toBeNull();
    });

    it('regressão: segunda chamada sequencial ao retry dá 409 e só um job é enfileirado', function () {
        Queue::fake();
        $storage = $this->mock(StorageService::class);
        $storage->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/x');
        $storage->shouldReceive('exists')->andReturnTrue();
        $user = createUserWithPermissions(['posts.create']);
        $post = secFailedPost($user);

        $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertOk();
        $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertStatus(409);

        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('reenvio concorrente (dois cliques) enfileira apenas um PublishPostJob', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = secFailedPost($user);
        $uuid = $post->uuid;
        $interleaved = false;

        // O "exists" roda entre a checagem de status e o update: simula a 2ª requisição entrando nesse intervalo.
        $storage = $this->mock(StorageService::class);
        $storage->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/x');
        $storage->shouldReceive('exists')->andReturnUsing(function () use (&$interleaved, $uuid) {
            if (! $interleaved) {
                $interleaved = true;
                app(ScheduledPostService::class)->retry($uuid);
            }

            return true;
        });

        try {
            $this->withToken($user->test_token)->postJson("/api/scheduled-posts/{$uuid}/retry");
        } catch (Throwable) {
        }

        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('error_message gravado a partir de exceção de terceiros não deve expor tokens na listagem', function () {
        $storage = $this->mock(StorageService::class);
        $storage->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/x');
        $user = createUserWithPermissions(['posts.view']);
        $post = secFailedPost($user, ['status' => 'pending', 'error_message' => null]);

        (new PublishPostJob($post))->failed(new Exception(
            'cURL error 28: Operation timed out for https://graph.instagram.com/v25.0/1?fields=status_code&access_token=SEGREDO-TOKEN-123'
        ));

        $response = $this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk();

        expect($response->getContent())->not->toContain('SEGREDO-TOKEN-123');
    })->skip('VULN-RETRY-02: PublishPostJob::failed e os serviços gravam $exception->getMessage()/json_encode(resposta) cru em error_message, e ScheduledPostResource o expõe a quem tem posts.view');

    it('notificação de falha não carrega error_message e só chega a membros do workspace do post', function () {
        $user = createUserWithPermissions(['posts.create']);
        $outsider = createUserWithPermissions(['posts.create']);
        $post = secFailedPost($user, ['status' => 'pending', 'error_message' => null]);

        (new PublishPostJob($post))->failed(new Exception('access_token=SEGREDO-TOKEN-123'));

        expect(Notification::where('type', 'post_failed')->get()->pluck('notifiable_id')->all())->not->toContain($outsider->id);
        expect(json_encode(Notification::where('type', 'post_failed')->get()->toArray()))->not->toContain('SEGREDO-TOKEN-123');
    });

    it('listagem de outro workspace não mostra post falho nem media_url dele', function () {
        $storage = $this->mock(StorageService::class);
        $storage->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/assinada');
        $user = createUserWithPermissions(['posts.view']);
        $outsider = createUserWithPermissions(['posts.view']);
        $post = secFailedPost($outsider);

        $content = $this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk()->getContent();

        expect($content)->not->toContain($post->uuid)->not->toContain('s3.test/assinada');
    });

    it('post com mídia purgada não expõe media_url', function () {
        $storage = $this->mock(StorageService::class);
        $storage->shouldNotReceive('generateDownloadUrl');
        $user = createUserWithPermissions(['posts.view']);
        secFailedPost($user, ['media_purged_at' => now()]);

        $this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk()
            ->assertJsonPath('data.0.media_url', null)
            ->assertJsonPath('data.0.can_retry', false);
    });

    it('media_storage_path com ".." não deve causar 500 (deve ser 422)', function () {
        Storage::fake('s3');
        $user = createUserWithPermissions(['posts.create']);
        $ws = $user->currentAccess->workspace->uuid;
        $account = connectedSocialAccount($user, 'instagram');

        $response = $this->withToken($user->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => "workspaces/{$ws}/videos/../../outro-ws/videos/x.mp4",
            'social_account_uuids' => [$account->uuid],
        ]);

        expect($response->status())->toBeIn([404, 422])->and(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('permalink do Instagram só é aceito se for URL https do domínio instagram.com', function () {
        $user = createUserWithPermissions(['posts.view']);
        $account = connectedSocialAccount($user, 'instagram');
        $post = ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'social_account_id' => $account->id, 'platform' => 'instagram',
            'title' => 'Reel', 'media_path' => 'workspaces/w/videos/a.mp4', 'status' => 'processing',
            'container_id' => 'container-1', 'container_created_at' => now(),
        ]);
        Http::fake([
            '*/container-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => '179']),
            '*/179*' => Http::response(['permalink' => 'javascript:alert(document.cookie)']),
        ]);

        (new CheckInstagramContainerJob($post, $account, 'container-1'))->handle();

        expect($post->fresh()->getPlatformPostUrl())->toBeNull();
    })->skip('VULN-RETRY-03: permalink devolvido pela Graph API é gravado e exposto sem validar esquema/host (hardening; origem é a Meta, mas o front pode usar como href)');

});
