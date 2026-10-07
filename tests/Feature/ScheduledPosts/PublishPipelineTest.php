<?php

use App\Contracts\SocialMediaServiceInterface;
use App\Exceptions\ScheduledPostException;
use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use App\Services\Factories\SocialMediaFactory;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
    Storage::fake('s3');

    $this->user = createUserWithPermissions(['posts.create', 'posts.delete']);
    $this->youtube = createSocialAccount('youtube', $this->user);
    $this->instagram = createSocialAccount('instagram', $this->user);
});

/**
 * Agenda o mesmo vídeo para YouTube e Instagram (dois posts com a mesma mídia).
 */
function scheduleForTwoAccounts($test): array
{
    $test->withToken($test->user->test_token)
        ->postJson('/api/scheduled-posts', [
            'media_storage_path' => uploadVideo($test->user),
            'social_account_uuids' => [$test->youtube->uuid, $test->instagram->uuid],
            'title' => 'Meu vídeo',
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertCreated();

    return ScheduledPost::orderBy('id')->get()->all();
}

describe('mídia compartilhada entre posts', function () {

    it('cancelar um post mantém a mídia que outro post ainda vai publicar', function () {
        [$youtubePost, $instagramPost] = scheduleForTwoAccounts($this);

        $this->withToken($this->user->test_token)
            ->deleteJson("/api/scheduled-posts/{$youtubePost->uuid}")
            ->assertOk();

        expect($youtubePost->fresh()->status)->toBe('cancelled');
        Storage::disk('s3')->assertExists($youtubePost->media_path);

        $this->withToken($this->user->test_token)
            ->deleteJson("/api/scheduled-posts/{$instagramPost->uuid}")
            ->assertOk();

        Storage::disk('s3')->assertMissing($youtubePost->media_path);
    });

    it('a falha definitiva de um post mantém a mídia que outro post ainda vai publicar', function () {
        [$youtubePost] = scheduleForTwoAccounts($this);

        (new PublishPostJob($youtubePost))->failed(new RuntimeException('falhou'));

        expect($youtubePost->fresh()->status)->toBe('failed')
            ->and($youtubePost->fresh()->error_message)->toBe('falhou');
        Storage::disk('s3')->assertExists($youtubePost->media_path);
    });

    it('mantém a thumbnail que outro post ativo ainda usa', function () {
        $payload = ['thumbnail_path' => 'workspaces/x/thumbnails/capa.jpg'];
        $published = createScheduledPost($this->youtube, ['payload' => $payload, 'media_path' => 'workspaces/x/videos/a.mp4']);
        createScheduledPost($this->instagram, ['payload' => $payload, 'media_path' => 'workspaces/x/videos/b.mp4']);
        Storage::disk('s3')->put('workspaces/x/thumbnails/capa.jpg', 'capa');
        Storage::disk('s3')->put('workspaces/x/videos/a.mp4', 'video');

        app(StorageService::class)->deletePostMedia($published);

        Storage::disk('s3')->assertMissing('workspaces/x/videos/a.mp4');
        Storage::disk('s3')->assertExists('workspaces/x/thumbnails/capa.jpg');
    });

    it('não cancela post de outro workspace', function () {
        [$youtubePost] = scheduleForTwoAccounts($this);
        $intruder = createUserWithPermissions(['posts.delete']);

        app('auth')->forgetGuards(); // o guard guarda o usuário da requisição anterior do mesmo teste

        $this->withToken($intruder->test_token)
            ->deleteJson("/api/scheduled-posts/{$youtubePost->uuid}")
            ->assertNotFound();

        expect($youtubePost->fresh()->status)->toBe('pending');
    });

    it('só cancela posts pendentes', function () {
        [$youtubePost] = scheduleForTwoAccounts($this);
        $youtubePost->update(['status' => 'processing']);

        $this->withToken($this->user->test_token)
            ->deleteJson("/api/scheduled-posts/{$youtubePost->uuid}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'cancel_not_allowed');
    });
});

describe('PublishPostJob', function () {

    it('grava no máximo 255 caracteres da mensagem de erro, sem quebrar acentos', function () {
        $post = createScheduledPost($this->youtube);

        (new PublishPostJob($post))->failed(new RuntimeException(str_repeat('ação ', 60)));

        $message = $post->fresh()->error_message;
        expect(mb_strlen($message))->toBe(255)
            ->and(mb_check_encoding($message, 'UTF-8'))->toBeTrue();
    });

    it('usa o tempo limite do supervisor da fila e espera entre as tentativas', function () {
        $job = new PublishPostJob(createScheduledPost($this->youtube));

        expect($job)->not->toHaveProperty('timeout')
            ->and($job->backoff)->toBe([30, 120])
            ->and($job->tries)->toBe(3)
            ->and($job->queue)->toBe('youtube');
    });

    it('lança erro de domínio quando a conta do post não existe mais', function () {
        $post = createScheduledPost($this->youtube);
        $this->youtube->delete();

        expect(fn () => app()->call([new PublishPostJob($post->fresh()), 'handle']))
            ->toThrow(ScheduledPostException::class);
    });

    it('chama o service da plataforma do post', function () {
        $post = createScheduledPost($this->youtube);
        $service = Mockery::mock(SocialMediaServiceInterface::class);
        $service->shouldReceive('upload')->once()->withArgs(fn ($account, $p) => $account->is($this->youtube) && $p->is($post));
        $this->mock(SocialMediaFactory::class)->shouldReceive('make')->andReturn($service);

        app()->call([new PublishPostJob($post), 'handle']);
    });
});
