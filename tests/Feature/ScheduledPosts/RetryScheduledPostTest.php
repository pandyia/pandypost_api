<?php

use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
    $this->storage = $this->mock(StorageService::class);
    $this->storage->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/video.mp4')->byDefault();
});

function failedPostFor(User $user, array $overrides = []): ScheduledPost
{
    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => connectedSocialAccount($user, 'instagram')->id,
        'platform' => 'instagram',
        'title' => 'Meu vídeo',
        'media_path' => 'workspaces/w/videos/video.mp4',
        'scheduled_at' => now()->subHour(),
        'status' => 'failed',
        'error_message' => 'Erro no processamento do container pelo Instagram',
        'container_id' => '123',
        'container_created_at' => now()->subHour(),
    ], $overrides));

    // Query direta não dispara os observers: define quando a falha aconteceu.
    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['failed_at' => now()->subDay()]);

    return $post->fresh();
}

describe('reenviar post que falhou', function () {

    it('reenvia na hora: volta para pending, limpa o erro e enfileira na fila da plataforma', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user);
        $this->storage->shouldReceive('exists')->with('workspaces/w/videos/video.mp4')->once()->andReturnTrue();

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertOk()
            ->assertJsonPath('message', 'Post reenviado para a fila.')
            ->assertJsonPath('data.uuid', $post->uuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.can_retry', false);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('pending')
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->failed_at)->toBeNull()
            ->and($fresh->scheduled_at)->toBeNull()
            ->and($fresh->container_id)->toBeNull()
            ->and($fresh->title)->toBe('Meu vídeo');

        Queue::assertPushedOn('instagram', PublishPostJob::class, fn ($job) => $job->post->is($post) && $job->delay === null);
    });

    it('com scheduled_at reagenda o post em vez de enviar na hora', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user);
        $this->storage->shouldReceive('exists')->andReturnTrue();
        $when = now()->addDays(2)->startOfSecond();

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry", ['scheduled_at' => $when->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        expect($post->fresh()->scheduled_at->equalTo($when))->toBeTrue();
        Queue::assertPushed(PublishPostJob::class, fn ($job) => $job->delay !== null);
    });

    it('retorna 422 com scheduled_at no passado ou muito próximo e nada muda', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user);
        $this->storage->shouldNotReceive('exists');

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry", ['scheduled_at' => now()->addMinute()->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        expect($post->fresh()->status)->toBe('failed');
        Queue::assertNothingPushed();
    });

    it('retorna 409 retry_not_allowed para post que não falhou e nada muda', function (string $status) {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user, ['status' => $status, 'error_message' => null]);
        $this->storage->shouldNotReceive('exists');

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(409)
            ->assertJsonPath('error', 'retry_not_allowed');

        expect($post->fresh()->status)->toBe($status);
        Queue::assertNothingPushed();
    })->with(['pending', 'processing', 'published', 'cancelled']);

    it('retorna 422 media_expired quando o job de limpeza já apagou a mídia', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user, ['media_purged_at' => now()->subHour()]);
        $this->storage->shouldNotReceive('exists');

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(422)
            ->assertJsonPath('error', 'media_expired');

        expect($post->fresh()->status)->toBe('failed');
        Queue::assertNothingPushed();
    });

    it('retorna 422 media_expired quando o arquivo não existe mais no storage (post antigo)', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user);
        $this->storage->shouldReceive('exists')->once()->andReturnFalse();

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(422)
            ->assertJsonPath('error', 'media_expired');

        expect($post->fresh()->status)->toBe('failed');
        Queue::assertNothingPushed();
    });

    it('retorna 403 sem a permissão posts.create', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.view']);
        $post = failedPostFor($user);

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertForbidden();

        expect($post->fresh()->status)->toBe('failed');
        Queue::assertNothingPushed();
    });

    it('retorna 404 para post de outro workspace e nada muda', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $outsider = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($outsider);
        $this->storage->shouldNotReceive('exists');

        $this->withToken($user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertNotFound();

        expect($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->not->toBeNull();
        Queue::assertNothingPushed();
    });

    it('permite a outro membro do workspace reenviar o post', function () {
        Queue::fake();
        $author = createUserWithPermissions(['posts.create']);
        $member = addUserToWorkspace($author->currentAccess->workspace);
        $member->currentAccess->role->permissions()->sync([permission('posts.create')->id]);
        $member->test_token = $member->createToken('test')->plainTextToken;
        $post = failedPostFor($author);
        $this->storage->shouldReceive('exists')->andReturnTrue();

        $this->withToken($member->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertOk();

        expect($post->fresh()->status)->toBe('pending');
    });

});

describe('can_retry na listagem', function () {

    it('é true só para post failed com a mídia guardada, e informa até quando', function () {
        $user = createUserWithPermissions(['posts.view']);
        $retryable = failedPostFor($user, ['title' => 'A']);
        failedPostFor($user, ['title' => 'B', 'media_purged_at' => now()]);
        failedPostFor($user, ['title' => 'C', 'status' => 'published']);

        $response = $this->withToken($user->test_token)
            ->getJson('/api/scheduled-posts')
            ->assertOk();

        $byTitle = collect($response->json('data'))->keyBy('title');
        expect($byTitle['A']['can_retry'])->toBeTrue()
            ->and($byTitle['A']['retry_until'])->toBe($retryable->failed_at->addDays(5)->toIso8601String())
            ->and($byTitle['B']['can_retry'])->toBeFalse()
            ->and($byTitle['B']['retry_until'])->toBeNull()
            ->and($byTitle['B']['media_url'])->toBeNull()
            ->and($byTitle['C']['can_retry'])->toBeFalse();
    });

});

describe('falha de publicação', function () {

    it('não apaga a mídia, marca failed_at e avisa que há 5 dias para reenviar', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user, ['status' => 'pending', 'error_message' => null, 'media_path' => 'workspaces/w/videos/a.mp4']);
        $this->storage->shouldNotReceive('deleteMany');
        $this->storage->shouldNotReceive('deleteIfUnused');

        (new PublishPostJob($post))->failed(new Exception('boom'));

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->failed_at)->not->toBeNull()
            ->and($fresh->media_purged_at)->toBeNull();

        $notification = Notification::where('type', 'post_failed')->first();
        expect($notification)->not->toBeNull()
            ->and($notification->data['message'])->toContain('5 dias para reenviar');
    });

    it('notifica outra vez quando o reenvio falha de novo', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user, ['status' => 'pending', 'error_message' => null]);

        (new PublishPostJob($post))->failed(new Exception('1ª falha'));
        $post->fresh()->update(['status' => 'pending']);
        $this->travel(1)->minute();
        (new PublishPostJob($post->fresh()))->failed(new Exception('2ª falha'));

        expect(Notification::where('type', 'post_failed')->count())->toBe(2);
    });

    it('avisa também quando o post é marcado failed fora do PublishPostJob (ex.: container do Instagram)', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = failedPostFor($user, ['status' => 'processing', 'error_message' => null]);

        $post->update(['status' => 'failed', 'error_message' => 'Erro no processamento do container pelo Instagram']);

        expect(Notification::where('type', 'post_failed')->count())->toBe(1);
    });

});
