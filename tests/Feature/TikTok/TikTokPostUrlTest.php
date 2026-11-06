<?php

use App\Models\ScheduledPost;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('RF-11: ScheduledPost::getPlatformPostUrl() do TikTok', function () {

    function tiktokUrlPost(?string $username, ?string $postId): ScheduledPost
    {
        $user = createUserWithPermissions([]);
        $account = connectedSocialAccount($user, 'tiktok', ['username' => $username]);

        return tiktokPost($user, $account, 'workspaces/w/videos/a.mp4', [], [
            'status' => 'published', 'platform_post_id' => $postId,
        ])->fresh();
    }

    it('com username e ID público monta https://www.tiktok.com/@{username}/video/{id}', function () {
        expect(tiktokUrlPost('canal_teste', '7390000000000000001')->getPlatformPostUrl())
            ->toBe('https://www.tiktok.com/@canal_teste/video/7390000000000000001');
    });

    it('sem username devolve null', function () {
        expect(tiktokUrlPost(null, '7390000000000000001')->getPlatformPostUrl())->toBeNull();
    });

    it('sem ID público (sandbox) devolve null', function () {
        expect(tiktokUrlPost('canal_teste', null)->getPlatformPostUrl())->toBeNull();
    });

    it('sem nenhum dos dois devolve null', function () {
        expect(tiktokUrlPost(null, null)->getPlatformPostUrl())->toBeNull();
    });

});
