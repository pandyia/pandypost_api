<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OAuthProviders\TikTokOAuthProvider;
use App\Services\Storage\StorageService;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = createUserWithPermissions(['social_accounts.connect', 'posts.create']);
        $this->workspace = Workspace::withoutGlobalScopes()->find($this->user->currentAccess->workspace_id);
    }

    public function test_tiktok_oauth_redirect_url_generation(): void
    {
        config([
            'services.tiktok.client_key' => 'test_client_key',
            'services.tiktok.redirect' => 'https://app.pandypost.com/auth/tiktok/callback',
        ]);

        $provider = new TikTokOAuthProvider();
        $url = $provider->getRedirectUrl($this->user);

        $this->assertStringContainsString('https://www.tiktok.com/v2/auth/authorize/', $url);
        $this->assertStringContainsString('client_key=test_client_key', $url);
        $this->assertStringContainsString('scope=user.info.basic%2Cvideo.publish%2Cvideo.upload', $url);
    }

    public function test_tiktok_account_sync_saves_social_account(): void
    {
        config([
            'services.tiktok.client_key' => 'test_client_key',
            'services.tiktok.client_secret' => 'test_client_secret',
            'services.tiktok.redirect' => 'https://app.pandypost.com/auth/tiktok/callback',
        ]);

        Http::fake([
            'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'mock_tiktok_access_token',
                'refresh_token' => 'mock_tiktok_refresh_token',
                'open_id' => 'tiktok_user_12345',
                'expires_in' => 86400,
            ]),
            'https://open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => [
                    'user' => [
                        'display_name' => 'TikTok Creator',
                        'avatar_url' => 'https://tiktok.com/avatar.jpg',
                    ],
                ],
            ]),
        ]);

        $provider = new TikTokOAuthProvider();
        $account = $provider->syncAccount($this->user, 'mock_auth_code');

        $this->assertInstanceOf(SocialAccount::class, $account);
        $this->assertEquals('tiktok', $account->platform);
        $this->assertEquals('tiktok_user_12345', $account->platform_id);
        $this->assertEquals('TikTok Creator', $account->nickname);
        $this->assertEquals('mock_tiktok_access_token', $account->access_token);
        $this->assertEquals('mock_tiktok_refresh_token', $account->refresh_token);
    }

    public function test_tiktok_token_refresh(): void
    {
        config([
            'services.tiktok.client_key' => 'test_client_key',
            'services.tiktok.client_secret' => 'test_client_secret',
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'platform' => Platform::TIKTOK->value,
            'platform_id' => 'tiktok_test_id',
            'nickname' => 'TikTok Test User',
            'access_token' => 'old_access_token',
            'refresh_token' => 'valid_refresh_token',
            'expires_at' => now()->subMinutes(10),
        ]);

        Http::fake([
            'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'new_refreshed_tiktok_access_token',
                'refresh_token' => 'new_refreshed_tiktok_refresh_token',
                'expires_in' => 86400,
            ]),
        ]);

        $token = $account->getValidToken();

        $this->assertEquals('new_refreshed_tiktok_access_token', $token);
        $this->assertDatabaseHas('social_accounts', [
            'id' => $account->id,
            'access_token' => 'new_refreshed_tiktok_access_token',
            'refresh_token' => 'new_refreshed_tiktok_refresh_token',
        ]);
    }

    public function test_tiktok_service_upload_initializes_post_and_dispatches_job(): void
    {
        Bus::fake();

        $account = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'platform' => Platform::TIKTOK->value,
            'platform_id' => 'tiktok_test_id',
            'nickname' => 'TikTok Test User',
            'access_token' => 'test_access_token',
            'expires_at' => now()->addHours(12),
        ]);

        $post = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'social_account_id' => $account->id,
            'platform' => Platform::TIKTOK->value,
            'media_path' => "workspaces/{$this->workspace->uuid}/videos/test.mp4",
            'caption' => 'Check out my TikTok video!',
            'payload' => [
                'tiktok_privacy_level' => 'PUBLIC_TO_EVERYONE',
                'tiktok_disable_comment' => false,
            ],
            'status' => 'pending',
        ]);

        $uploadUrl = 'https://open-api.tiktok.com/media/upload/v1/test';

        Http::fake([
            'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
                'data' => [
                    'publish_id' => 'v_pub_file_test_999',
                    'upload_url' => $uploadUrl,
                ],
                'error' => [
                    'code' => 'ok',
                    'message' => '',
                ],
            ]),
            $uploadUrl => Http::response([], 200),
        ]);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'dummy video stream data');
        rewind($stream);

        $storageServiceMock = $this->createMock(StorageService::class);
        $storageServiceMock->expects($this->once())
            ->method('size')
            ->willReturn(23);
        $storageServiceMock->expects($this->once())
            ->method('readStream')
            ->willReturn($stream);

        $tiktokService = new TikTokService($storageServiceMock);
        $tiktokService->upload($account, $post);

        $this->assertEquals('v_pub_file_test_999', $post->fresh()->container_id);
        $this->assertEquals('processing', $post->fresh()->status);

        Bus::assertDispatched(CheckTikTokPostStatusJob::class, function ($job) use ($post, $account) {
            return $job->publishId === 'v_pub_file_test_999'
                && $job->post->id === $post->id
                && $job->account->id === $account->id;
        });
    }

    public function test_check_tiktok_post_status_job_handles_publish_complete(): void
    {
        $account = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'platform' => Platform::TIKTOK->value,
            'platform_id' => 'tiktok_test_id',
            'nickname' => 'TikTok Test User',
            'access_token' => 'valid_token',
            'expires_at' => now()->addHours(12),
        ]);

        $post = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'social_account_id' => $account->id,
            'platform' => Platform::TIKTOK->value,
            'media_path' => "workspaces/{$this->workspace->uuid}/videos/test.mp4",
            'container_id' => 'v_pub_file_test_999',
            'status' => 'processing',
        ]);

        Http::fake([
            'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
                'data' => [
                    'status' => 'PUBLISH_COMPLETE',
                    'publicity_id' => '72384910293840192',
                ],
            ]),
        ]);

        $storageServiceMock = $this->createMock(StorageService::class);
        $storageServiceMock->expects($this->once())
            ->method('deleteIfUnused')
            ->with([$post->media_path], $post->id)
            ->willReturn(true);

        $job = new CheckTikTokPostStatusJob($post, $account, 'v_pub_file_test_999');
        $job->handle($storageServiceMock);

        $freshPost = $post->fresh();
        $this->assertEquals('published', $freshPost->status);
        $this->assertEquals('72384910293840192', $freshPost->platform_post_id);
        $this->assertNotNull($freshPost->published_at);
    }

    public function test_tiktok_service_upload_handles_unaudited_client_error(): void
    {
        Bus::fake();

        $account = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'platform' => Platform::TIKTOK->value,
            'platform_id' => 'tiktok_test_id',
            'nickname' => 'TikTok Test User',
            'access_token' => 'test_access_token',
            'expires_at' => now()->addHours(12),
        ]);

        $post = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'social_account_id' => $account->id,
            'platform' => Platform::TIKTOK->value,
            'media_path' => "workspaces/{$this->workspace->uuid}/videos/test.mp4",
            'caption' => 'Check out my TikTok video!',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
                'error' => [
                    'code' => 'unaudited_client_can_only_post_to_private_accounts',
                    'message' => 'Please review our integration guidelines',
                ],
            ]),
        ]);

        $storageServiceMock = $this->createMock(StorageService::class);
        $storageServiceMock->expects($this->once())->method('size')->willReturn(23);

        $tiktokService = new TikTokService($storageServiceMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('App do TikTok não auditado');

        try {
            $tiktokService->upload($account, $post);
        } finally {
            $this->assertEquals('failed', $post->fresh()->status);
            $this->assertStringContainsString('App do TikTok não auditado', $post->fresh()->error_message);
        }
    }
}
