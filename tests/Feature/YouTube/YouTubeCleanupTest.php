<?php

use App\Services\Storage\StorageService;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Spec 018 RF-9: o vídeo já está no ar; falha ao limpar o S3 não pode virar retry (vídeo duplicado).

it('falha ao apagar a mídia do S3 depois do upload não lança e o post fica published', function () {
    Storage::fake('s3');
    $user = createUserWithPermissions(['posts.create']);
    $account = youtubeAccount($user);
    $post = youtubePost($user, $account, youtubeStoredFile($user, 'v.mp4'));
    youtubeFakeSdk([youtubeResumeResponse(), youtubeDoneResponse('yt-ok')]);

    $storage = Mockery::mock(StorageService::class)->makePartial();
    $storage->shouldReceive('deleteIfUnused')->andThrow(new RuntimeException('S3 fora do ar'));
    $service = new YouTubeService($storage);

    $service->upload($account, $post);

    $fresh = $post->fresh();
    expect($fresh->status)->toBe('published')->and($fresh->platform_post_id)->toBe('yt-ok');
});
