<?php

use App\Exceptions\ScheduledPostException;
use App\Exceptions\TikTokException;
use App\Jobs\CheckInstagramContainerJob;
use App\Jobs\CheckTikTokPostStatusJob;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\ScheduledPostService;
use App\Services\Storage\StorageService;
use App\Services\TikTok\TikTokApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Spec 019: fechamento da publicação (D1 cancelar/reenviar por permissão, mídia compartilhada, falha fechada).

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('s3');
});

function fpPost(User $author, SocialAccount $account, string $status = 'pending', array $overrides = []): ScheduledPost
{
    return ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $author->id,
        'social_account_id' => $account->id,
        'platform' => $account->platform,
        'title' => 't',
        'media_path' => 'workspaces/w/videos/fp.mp4',
        'status' => $status,
    ], $overrides));
}

describe('D1: cancelar e reenviar por permissão no workspace', function () {

    it('outro membro com posts.delete cancela o post de quem agendou', function () {
        Queue::fake();
        $autor = createUserWithPermissions(['posts.create']);
        $post = fpPost($autor, connectedSocialAccount($autor, 'youtube'));
        $colega = createUserWithPermissions(['posts.delete'], true, $autor->currentAccess->workspace);

        $this->withToken($colega->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertOk();

        expect($post->fresh()->status)->toBe('cancelled');
    });

    it('membro sem posts.delete continua recebendo 403 ao cancelar post do colega', function () {
        $autor = createUserWithPermissions(['posts.create']);
        $post = fpPost($autor, connectedSocialAccount($autor, 'youtube'));
        $colega = createUserWithPermissions(['posts.create'], true, $autor->currentAccess->workspace);

        $this->withToken($colega->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertForbidden();

        expect($post->fresh()->status)->toBe('pending');
    });

    it('usuário de outro workspace recebe 404 ao cancelar e nada muda', function () {
        $autor = createUserWithPermissions(['posts.create']);
        $post = fpPost($autor, connectedSocialAccount($autor, 'youtube'));
        $estranho = createUserWithPermissions(['posts.delete']);

        $this->withToken($estranho->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertNotFound();

        expect($post->fresh()->status)->toBe('pending');
    });

    it('outro membro com posts.create reenvia o post falho de quem agendou', function () {
        Queue::fake();
        $autor = createUserWithPermissions(['posts.create']);
        $path = 'workspaces/w/videos/retry.mp4';
        Storage::disk('s3')->put($path, 'v');
        $post = fpPost($autor, connectedSocialAccount($autor, 'youtube'), 'failed', ['media_path' => $path, 'failed_at' => now(), 'error_message' => 'x']);
        $colega = createUserWithPermissions(['posts.create'], true, $autor->currentAccess->workspace);

        $this->withToken($colega->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertOk();

        expect($post->fresh()->status)->toBe('pending');
    });
});

describe('mídia compartilhada', function () {

    it('desconectar a conta mantém a mídia que outro post de outra conta ainda vai publicar', function () {
        $user = createUserWithPermissions(['social_accounts.disconnect']);
        $path = 'workspaces/w/videos/shared.mp4';
        Storage::disk('s3')->put($path, 'v');
        $a = connectedSocialAccount($user, 'youtube');
        $b = connectedSocialAccount($user, 'youtube');
        fpPost($user, $a, 'pending', ['media_path' => $path]);
        $irmao = fpPost($user, $b, 'pending', ['media_path' => $path]);

        $this->withToken($user->test_token)->deleteJson("/api/social-accounts/{$a->uuid}")->assertOk();

        expect(Storage::disk('s3')->exists($path))->toBeTrue()
            ->and($irmao->fresh()->status)->toBe('pending');
    });

    it('desconectar a conta apaga a mídia dos posts pendentes que ninguém mais usa', function () {
        $user = createUserWithPermissions(['social_accounts.disconnect']);
        $path = 'workspaces/w/videos/solo.mp4';
        $thumb = 'workspaces/w/thumbnails/solo.jpg';
        Storage::disk('s3')->put($path, 'v');
        Storage::disk('s3')->put($thumb, 'i');
        $a = connectedSocialAccount($user, 'youtube');
        $post = fpPost($user, $a, 'pending', ['media_path' => $path, 'payload' => ['thumbnail_path' => $thumb]]);

        $this->withToken($user->test_token)->deleteJson("/api/social-accounts/{$a->uuid}")->assertOk();

        expect(Storage::disk('s3')->exists($path))->toBeFalse()
            ->and(Storage::disk('s3')->exists($thumb))->toBeFalse()
            ->and(ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->value('status'))->toBe('cancelled');
    });
});

describe('BelongsToWorkspace falha fechado (VULN-TEN-02)', function () {

    it('usuário autenticado sem currentAccess não enxerga posts nem contas de nenhum workspace', function () {
        $dono = createUserWithPermissions(['posts.view']);
        fpPost($dono, connectedSocialAccount($dono, 'youtube'));
        $semAcesso = User::factory()->create();

        $this->actingAs($semAcesso);

        expect(ScheduledPost::count())->toBe(0)
            ->and(SocialAccount::count())->toBe(0)
            ->and(ScheduledPost::withoutGlobalScopes()->count())->toBe(1);
    });

    it('resolveCurrentAccess não deixa a relação nula em cache (usuário com workspace pessoal e access_id nulo)', function () {
        $user = createUserWithPermissions(['posts.view']);
        $post = fpPost($user, connectedSocialAccount($user, 'youtube'));
        $user = User::findOrFail($user->id);
        User::whereKey($user->id)->update(['access_id' => null]);
        $user->refresh();
        $this->actingAs($user);

        expect(ScheduledPost::count())->toBe(0);

        $access = $user->resolveCurrentAccess();

        expect($access)->not->toBeNull()
            ->and($user->currentAccess->is($access))->toBeTrue()
            ->and(ScheduledPost::pluck('id')->all())->toBe([$post->id]);
    });

    it('sem usuário autenticado (jobs, comandos, webhooks) o escopo não se aplica: deleteIfUnused enxerga posts de todos os workspaces', function () {
        $user = createUserWithPermissions([]);
        $path = 'workspaces/w/videos/job.mp4';
        Storage::disk('s3')->put($path, 'v');
        fpPost($user, connectedSocialAccount($user, 'youtube'), 'pending', ['media_path' => $path]);

        expect(auth()->hasUser())->toBeFalse()
            ->and(app(StorageService::class)->deleteIfUnused([$path]))->toBeTrue()
            ->and(Storage::disk('s3')->exists($path))->toBeTrue();
    });

    it('agendar com usuário sem currentAccess e sem workspace pessoal não acha conta alguma', function () {
        $dono = createUserWithPermissions(['posts.create']);
        $conta = connectedSocialAccount($dono, 'youtube');
        $semAcesso = User::factory()->create();

        $this->actingAs($semAcesso);

        expect(fn () => app(ScheduledPostService::class)->schedule($semAcesso, [
            'media_storage_path' => 'workspaces/x/videos/a.mp4', 'social_account_uuids' => [$conta->uuid], 'title' => 't',
        ]))->toThrow(ScheduledPostException::class);
    });
});

describe('D2: TikTok sem permissão de publicar', function () {

    it('422 no agendamento, tudo ou nada: nenhum post (nem das outras contas) é criado nem enfileirado', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $midia = tiktokMedia($user);
        $yt = connectedSocialAccount($user, 'youtube');
        $semEscopo = connectedSocialAccount($user, 'tiktok', ['scopes' => 'user.info.basic,video.upload']);

        $response = $this->withToken($user->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $midia, 'social_account_uuids' => [$yt->uuid, $semEscopo->uuid],
            'title' => 'T', 'privacy_level' => 'SELF_ONLY',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('social_account_uuids');
        expect($response->json('errors.social_account_uuids.0'))->toContain('Reconecte a conta do TikTok')
            ->and(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('conta TikTok com video.publish agenda normalmente (201)', function () {
        Queue::fake();
        $user = createUserWithPermissions(['posts.create']);
        $midia = tiktokMedia($user);
        $conta = connectedSocialAccount($user, 'tiktok', ['scopes' => TIKTOK_SCOPES_OK]);

        $this->withToken($user->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $midia, 'social_account_uuids' => [$conta->uuid], 'title' => 'T', 'privacy_level' => 'SELF_ONLY',
        ])->assertCreated();
    });
});

describe('no_account_linked', function () {

    it('conta de outro workspace: 400 com texto claro, sem uuid nem nome de rede', function () {
        $user = createUserWithPermissions(['posts.create']);
        $midia = tiktokMedia($user);
        $outro = createUserWithPermissions([]);
        $conta = connectedSocialAccount($outro, 'youtube');

        $response = $this->withToken($user->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $midia, 'social_account_uuids' => [$conta->uuid], 'title' => 'T',
        ]);

        $response->assertStatus(400)->assertJsonPath('error', 'no_account_linked')
            ->assertJsonPath('message', 'A conta selecionada não está disponível neste workspace.');
        expect($response->getContent())->not->toContain($conta->uuid);
    });
});

describe('TikTok: upload sem redirecionamento e estado final', function () {

    it('o PUT do chunk não segue redirecionamento (302 vira falha de upload e só uma requisição sai)', function () {
        Http::fake([
            'open-upload.tiktokapis.com/*' => Http::response('', 302, ['Location' => 'https://evil.test/colete']),
            'evil.test/*' => Http::response('ok', 200),
        ]);

        expect(fn () => app(TikTokApi::class)->uploadChunk(TIKTOK_UPLOAD_URL, 'abc', 0, 2, 3, 'video/mp4'))
            ->toThrow(TikTokException::class);

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.test'));
    });

    it('FAILED do TikTok não sobrescreve post já publicado nem cancelado', function () {
        foreach (['published', 'cancelled'] as $status) {
            $user = createUserWithPermissions([]);
            $conta = tiktokAccount($user);
            $post = fpPost($user, $conta, $status, ['container_id' => 'pub-1', 'error_message' => null]);
            Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['status' => 'FAILED', 'fail_reason' => 'motivo'], 'error' => ['code' => 'ok']])]);

            (new CheckTikTokPostStatusJob($post->fresh(), $conta, 'pub-1'))->handle(app(StorageService::class), app(TikTokApi::class));

            expect($post->fresh()->status)->toBe($status)->and($post->fresh()->error_message)->toBeNull();
        }
    });

    it('failed() do job com o post cancelado no banco também não o marca como falho', function () {
        $user = createUserWithPermissions([]);
        $conta = tiktokAccount($user);
        $post = fpPost($user, $conta, 'processing', ['container_id' => 'pub-1']);
        $job = new CheckTikTokPostStatusJob($post, $conta, 'pub-1');
        ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['status' => 'cancelled']);

        $job->failed(TikTokException::publishTimeout());

        expect($post->fresh()->status)->toBe('cancelled');
    });
});

describe('permalink do Instagram', function () {

    function fpInstagramPublish(string|array $permalink): ScheduledPost
    {
        $user = createUserWithPermissions(['posts.view']);
        $account = connectedSocialAccount($user, 'instagram');
        $post = fpPost($user, $account, 'processing', ['container_id' => 'container-1', 'container_created_at' => now()]);
        Http::fake([
            '*/container-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => '179']),
            '*/179*' => Http::response(['permalink' => $permalink]),
        ]);

        (new CheckInstagramContainerJob($post, $account, 'container-1'))->handle();

        return $post->fresh();
    }

    it('aceita https do instagram.com e de subdomínio', function (string $url) {
        $post = fpInstagramPublish($url);

        expect($post->status)->toBe('published')->and($post->platform_post_url)->toBe($url);
    })->with(['https://www.instagram.com/reel/ABC123/', 'https://instagram.com/p/XYZ/']);

    it('recusa esquema ou host fora do Instagram e fecha como publicado sem link', function (string $url) {
        $post = fpInstagramPublish($url);

        expect($post->status)->toBe('published')->and($post->platform_post_url)->toBeNull();
    })->with([
        'javascript:alert(1)',
        'http://www.instagram.com/reel/ABC/',
        'https://evil.test/instagram.com/reel/ABC/',
        'https://instagram.com.evil.test/reel/ABC/',
        'https://evilinstagram.com/reel/ABC/',
        'https://user:pass@evil.test@instagram.com.evil.test/',
    ]);
});

describe('OAuth callback (spec 019)', function () {

    it('state da plataforma A não vale em B e é consumido (o state certo, depois, também falha)', function () {
        config(['app.frontend_url' => 'https://front.test', 'cache.default' => 'array']);
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'google');
        Http::fake();

        $this->get('/api/social-accounts/tiktok/callback?code=c&state='.urlencode($state))
            ->assertRedirectContains('error='.urlencode('Estado OAuth inválido.'));
        $this->get('/api/social-accounts/google/callback?code=c&state='.urlencode($state))
            ->assertRedirectContains('error='.urlencode('Estado OAuth inválido.'));

        Http::assertNothingSent();
    });

    it('iniciador sem social_accounts.connect recebe mensagem de permissão e nenhuma chamada à rede', function () {
        config(['app.frontend_url' => 'https://front.test', 'cache.default' => 'array']);
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');
        $user->currentAccess->role->permissions()->sync([]);
        Http::fake();

        $this->get('/api/social-accounts/tiktok/callback?code=c&state='.urlencode($state))
            ->assertRedirectContains('error='.urlencode('Você não tem permissão para conectar contas neste workspace.'));

        Http::assertNothingSent();
    });
});
