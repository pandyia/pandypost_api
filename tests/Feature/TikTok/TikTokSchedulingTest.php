<?php

use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
    Storage::fake('s3');
});

function uploadTikTokVideo(User $user): string
{
    $workspaceUuid = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id)->uuid;
    $path = "workspaces/{$workspaceUuid}/videos/video.mp4";

    Storage::disk('s3')->put($path, 'video');

    return $path;
}

describe('agendar post no TikTok', function () {

    it('guarda as opções do TikTok no payload do post e agenda a publicação', function () {
        $user = createUserWithPermissions(['posts.create']);
        $account = createTikTokAccount($user);

        $this->withToken($user->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadTikTokVideo($user),
                'social_account_uuids' => [$account->uuid],
                'caption' => 'Meu vídeo',
                'tiktok_privacy_level' => 'SELF_ONLY',
                'tiktok_disable_comment' => true,
                'tiktok_disable_duet' => false,
                'tiktok_disable_stitch' => true,
                'tiktok_brand_content_toggle' => false,
            ])
            ->assertCreated();

        $post = ScheduledPost::sole();
        expect($post->platform->value)->toBe('tiktok')
            ->and($post->payload)->toBe([
                'tiktok_privacy_level' => 'SELF_ONLY',
                'tiktok_disable_comment' => true,
                'tiktok_disable_duet' => false,
                'tiktok_disable_stitch' => true,
                'tiktok_brand_content_toggle' => false,
            ]);

        Bus::assertDispatched(PublishPostJob::class);
    });

    it('recusa privacidade inválida do TikTok', function () {
        $user = createUserWithPermissions(['posts.create']);
        $account = createTikTokAccount($user);

        $this->withToken($user->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadTikTokVideo($user),
                'social_account_uuids' => [$account->uuid],
                'tiktok_privacy_level' => 'PUBLIC',
                'tiktok_disable_comment' => 'talvez',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tiktok_privacy_level', 'tiktok_disable_comment']);

        expect(ScheduledPost::count())->toBe(0);
    });

    it('retorna 403 para quem não tem permissão de criar posts', function () {
        $user = createUserWithoutPermissions();
        $account = createTikTokAccount($user);

        $this->withToken($user->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadTikTokVideo($user),
                'social_account_uuids' => [$account->uuid],
            ])
            ->assertForbidden();
    });

    it('não permite agendar na conta TikTok de outro workspace', function () {
        $owner = createUserWithPermissions(['posts.create']);
        $intruder = createUserWithPermissions(['posts.create']);
        $account = createTikTokAccount($owner);

        $this->withToken($intruder->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadTikTokVideo($intruder),
                'social_account_uuids' => [$account->uuid],
            ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'no_account_linked');

        expect(ScheduledPost::count())->toBe(0);
        Bus::assertNotDispatched(PublishPostJob::class);
    });
});
