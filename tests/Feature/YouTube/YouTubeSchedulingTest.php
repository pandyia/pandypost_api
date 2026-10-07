<?php

use App\Jobs\PublishPostJob;
use App\Models\ScheduledPost;
use App\Models\YouTubeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
    Storage::fake('s3');

    $this->user = createUserWithPermissions(['posts.create']);
    $this->account = createSocialAccount('youtube', $this->user);
});

describe('agendar post no YouTube', function () {

    it('guarda as opções do YouTube no payload, sem espaços nas tags', function () {
        $this->withToken($this->user->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadVideo($this->user),
                'social_account_uuids' => [$this->account->uuid],
                'title' => 'Meu vídeo',
                'is_short' => true,
                'youtube_privacy_status' => 'private',
                'youtube_category_id' => '20',
                'youtube_tags' => [' jogos ', 'tutorial'],
                'youtube_made_for_kids' => false,
            ])
            ->assertCreated();

        expect(ScheduledPost::sole()->payload)->toBe([
            'is_short' => true,
            'youtube_privacy_status' => 'private',
            'youtube_category_id' => '20',
            'youtube_tags' => ['jogos', 'tutorial'],
            'youtube_made_for_kids' => false,
        ]);

        Bus::assertDispatched(PublishPostJob::class);
    });

    it('recusa privacidade inválida do YouTube', function () {
        $this->withToken($this->user->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadVideo($this->user),
                'social_account_uuids' => [$this->account->uuid],
                'title' => 'Meu vídeo',
                'youtube_privacy_status' => 'secreto',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['youtube_privacy_status']);
    });

    it('exige título para agendar no YouTube', function () {
        $this->withToken($this->user->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadVideo($this->user),
                'social_account_uuids' => [$this->account->uuid],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'Para postar no YouTube, você precisa definir um título.');

        expect(ScheduledPost::count())->toBe(0);
    });

    it('não exige título quando nenhuma conta é do YouTube', function () {
        $tiktok = createSocialAccount('tiktok', $this->user);

        $this->withToken($this->user->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadVideo($this->user),
                'social_account_uuids' => [$tiktok->uuid],
            ])
            ->assertCreated();
    });

    it('mostra a mensagem em português para privacidade inválida', function () {
        $this->withToken($this->user->test_token)
            ->postJson('/api/scheduled-posts', [
                'media_storage_path' => uploadVideo($this->user),
                'social_account_uuids' => [$this->account->uuid],
                'title' => 'Meu vídeo',
                'youtube_privacy_status' => 'secreto',
            ])
            ->assertJsonPath('errors.youtube_privacy_status.0', 'A privacidade do YouTube deve ser public, private ou unlisted.');
    });
});

describe('dados de apoio do YouTube', function () {

    it('lista as privacidades disponíveis', function () {
        $this->withToken($this->user->test_token)
            ->getJson('/api/youtube-privacy-statuses')
            ->assertOk()
            ->assertExactJson([
                ['value' => 'public', 'label' => 'Público'],
                ['value' => 'private', 'label' => 'Privado'],
                ['value' => 'unlisted', 'label' => 'Não listado'],
            ]);
    });

    it('lista as categorias cadastradas', function () {
        YouTubeCategory::create(['id' => 22, 'name' => 'Pessoas e blogs']);

        $this->withToken($this->user->test_token)
            ->getJson('/api/youtube-categories')
            ->assertOk()
            ->assertJsonFragment(['id' => 22, 'name' => 'Pessoas e blogs']);
    });
});
