<?php

use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Corpo JSON do videos.insert enviado pelo SDK (a requisição que carrega snippet/status). */
function youtubeInsertedMetadata(array $payload, array $postOverrides = []): array
{
    Storage::fake('s3');
    $user = createUserWithPermissions(['posts.create']);
    $account = youtubeAccount($user);
    $post = youtubePost($user, $account, youtubeStoredFile($user, 'video.mp4'), $payload, $postOverrides);
    $history = youtubeFakeSdk([youtubeResumeResponse(), youtubeDoneResponse()]);

    app(YouTubeService::class)->upload($account, $post);

    $insert = collect($history)->first(fn ($h) => str_contains((string) $h['request']->getUri(), 'videos'))['request'];
    $insert->getBody()->rewind();

    return json_decode((string) $insert->getBody(), true);
}

describe('metadados do vídeo no YouTube (caracterização)', function () {

    it('usa os padrões: público, categoria 22 e não é para crianças', function () {
        $metadata = youtubeInsertedMetadata([]);

        expect($metadata['snippet']['title'])->toBe('Meu vídeo')
            ->and($metadata['snippet']['description'])->toBe('Descrição do vídeo')
            ->and($metadata['snippet']['categoryId'])->toBe('22')
            ->and($metadata['snippet'])->not->toHaveKey('tags')
            ->and($metadata['status']['privacyStatus'])->toBe('public')
            ->and($metadata['status']['selfDeclaredMadeForKids'])->toBeFalse();
    });

    it('respeita categoria, privacidade, público infantil e tags (com trim) do payload', function () {
        $metadata = youtubeInsertedMetadata([
            'youtube_category_id' => 10,
            'youtube_privacy_status' => 'unlisted',
            'youtube_made_for_kids' => true,
            'youtube_tags' => ['  laravel ', 'php'],
        ]);

        expect($metadata['snippet']['categoryId'])->toBe('10')
            ->and($metadata['snippet']['tags'])->toBe(['laravel', 'php'])
            ->and($metadata['status']['privacyStatus'])->toBe('unlisted')
            ->and($metadata['status']['selfDeclaredMadeForKids'])->toBeTrue();
    });

    it('legenda ausente vira descrição vazia', function () {
        $metadata = youtubeInsertedMetadata([], ['caption' => null]);

        expect($metadata['snippet']['description'] ?? '')->toBe('');
    });
});
