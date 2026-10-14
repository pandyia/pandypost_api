<?php

use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->storage = $this->mock(StorageService::class);
});

function scheduledPostFor(User $user, array $overrides = []): ScheduledPost
{
    $account = SocialAccount::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'workspace_id' => $user->currentAccess->workspace_id,
        'platform' => 'youtube',
        'platform_id' => 'canal-'.uniqid(),
        'access_token' => 'token-falso',
        'nickname' => 'Canal',
    ]);

    return ScheduledPost::create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => 'youtube',
        'media_path' => 'workspaces/w/videos/video.mp4',
        'payload' => ['thumbnail_path' => 'workspaces/w/images/thumb.jpg'],
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ], $overrides));
}

describe('cancelar post agendado', function () {

    it('cancela um post pendente e remove a mídia do storage', function () {
        $user = createUserWithPermissions(['posts.delete']);
        $post = scheduledPostFor($user);

        $this->storage->shouldReceive('deleteMany')
            ->once()
            ->with(['workspaces/w/videos/video.mp4', 'workspaces/w/images/thumb.jpg'])
            ->andReturnTrue();

        $this->withToken($user->test_token)
            ->deleteJson("/api/scheduled-posts/{$post->uuid}")
            ->assertOk();

        expect($post->fresh()->status)->toBe('cancelled');
    });

    it('retorna 409 cancel_not_allowed para post já publicado', function () {
        $user = createUserWithPermissions(['posts.delete']);
        $post = scheduledPostFor($user, ['status' => 'published']);

        $this->storage->shouldNotReceive('deleteMany');

        $this->withToken($user->test_token)
            ->deleteJson("/api/scheduled-posts/{$post->uuid}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'cancel_not_allowed');

        expect($post->fresh()->status)->toBe('published');
    });

    it('retorna 404 ao cancelar post de outro usuário', function () {
        $user = createUserWithPermissions(['posts.delete']);
        $post = scheduledPostFor(createUserWithPermissions([]));

        $this->storage->shouldNotReceive('deleteMany');

        $this->withToken($user->test_token)
            ->deleteJson("/api/scheduled-posts/{$post->uuid}")
            ->assertNotFound();

        expect($post->fresh()->status)->toBe('pending');
    });

    it('retorna 403 sem a permissão posts.delete', function () {
        $user = createUserWithPermissions(['posts.view']);
        $post = scheduledPostFor($user);

        $this->withToken($user->test_token)
            ->deleteJson("/api/scheduled-posts/{$post->uuid}")
            ->assertForbidden();

        expect($post->fresh()->status)->toBe('pending');
    });

});

describe('gerar url de upload', function () {

    it('retorna 403 sem a permissão posts.create', function () {
        $user = createUserWithPermissions(['posts.view']);

        $this->withToken($user->test_token)
            ->postJson('/api/scheduled-posts/upload-url', [])
            ->assertForbidden();
    });

    it('retorna 422 sem os campos obrigatórios', function () {
        $user = createUserWithPermissions(['posts.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/scheduled-posts/upload-url', [])
            ->assertUnprocessable();
    });

});
