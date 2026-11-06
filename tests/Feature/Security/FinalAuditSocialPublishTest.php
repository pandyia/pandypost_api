<?php

use App\Jobs\CheckInstagramContainerJob;
use App\Jobs\PublishPostJob;
use App\Models\Access;
use App\Models\Role;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Factories\SocialMediaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Auditoria final (specs 011, 014-018): OAuth, permissões e esteira de publicação.

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['app.frontend_url' => 'https://front.test', 'cache.default' => 'array']);
});

function faCallback(string $platform, string $state)
{
    return test()->get("/api/social-accounts/{$platform}/callback?code=c1&state=".urlencode($state));
}

describe('OAuth callback', function () {

    it('VULN-OAUTH-04: erro inesperado no callback não devolve a mensagem crua (SQL com tokens) na URL do redirect', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response(['access_token' => 'ACCESS-SECRETO-777', 'refresh_token' => 'REFRESH-SECRETO-888', 'open_id' => 'oid-1', 'expires_in' => 86400, 'scope' => 'video.publish']),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'x']], 'error' => ['code' => 'ok']]),
        ]);
        // Simula o "Data too long" do MySQL (nickname/avatar > 255) com um erro de banco real no INSERT.
        DB::unprepared("CREATE TRIGGER fa_fail BEFORE INSERT ON social_accounts BEGIN SELECT RAISE(ABORT, 'Data too long for column'); END;");

        $response = faCallback('tiktok', $state);

        $location = urldecode((string) $response->headers->get('Location'));
        expect($location)->not->toContain('ACCESS-SECRETO-777')
            ->and($location)->not->toContain('REFRESH-SECRETO-888')
            ->and($location)->not->toContain('SQLSTATE')
            ->and($location)->not->toContain('insert into');
    });

    it('VULN-OAUTH-05: o state de um fluxo (google) não vale no callback de outra rede (instagram)', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'google');
        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short', 'user_id' => 'ig-1']),
            'graph.instagram.com/*' => Http::response(['id' => 'ig-1', 'username' => 'u', 'access_token' => 'long', 'expires_in' => 5184000]),
        ]);

        faCallback('instagram', $state);

        expect(SocialAccount::withoutGlobalScopes()->where('platform', 'instagram')->count())->toBe(0);
    });

    it('VULN-OAUTH-06: iniciador que perdeu social_accounts.connect antes do callback não conecta conta', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');
        // O papel do iniciador é esvaziado entre o início e o callback (continua membro do workspace).
        $user->currentAccess->role->permissions()->sync([]);
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'open_id' => 'oid-9', 'expires_in' => 86400, 'scope' => 'video.publish']),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'x']], 'error' => ['code' => 'ok']]),
        ]);

        faCallback('tiktok', $state);

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });

    it('VULN-OAUTH-07: o callback público tem rate limit', function () {
        $route = Route::getRoutes()->getByName('') ?? collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/social-accounts/{platform}/callback');

        $middleware = implode(',', $route->gatherMiddleware());

        expect($middleware)->toContain('throttle');
    });

    it('regressão: o redirect do callback nunca contém code, state nem token em caso de falha de conexão com a rede', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');
        Http::fake(['open.tiktokapis.com/*' => fn () => throw new ConnectionException('cURL error 28 for https://open.tiktokapis.com/v2/oauth/token/')]);

        $location = urldecode((string) faCallback('tiktok', $state)->headers->get('Location'));

        expect($location)->toStartWith('https://front.test/social-accounts/callback?error=')
            ->and($location)->not->toContain($state)
            ->and(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
    });

    it('regressão: state emitido para o usuário A, com A fora do workspace, não cria conta em nenhum workspace', function () {
        $user = createUserWithPermissions(['social_accounts.connect']);
        $state = oauthStateFor($user, 'tiktok');
        Access::withoutGlobalScopes()->where('user_id', $user->id)->delete();
        Http::fake();

        faCallback('tiktok', $state)->assertRedirect();

        expect(SocialAccount::withoutGlobalScopes()->count())->toBe(0);
        Http::assertNothingSent();
    });
});

describe('Permissões e isolamento', function () {

    it('VULN-SCHED-01: GET /social-accounts/schedules exige social_accounts.view e posts.view (hoje qualquer membro lê contas e posts)', function () {
        Storage::fake('s3');
        $owner = createUserWithPermissions(['social_accounts.view', 'posts.view']);
        $conta = connectedSocialAccount($owner, 'youtube');
        ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $owner->id, 'social_account_id' => $conta->id, 'platform' => 'youtube',
            'title' => 'Lançamento confidencial', 'caption' => 'segredo', 'media_path' => null, 'status' => 'pending',
        ]);
        $semPermissao = createUserWithPermissions([], true, Workspace::withoutGlobalScopes()->find($owner->currentAccess->workspace_id));

        $response = $this->withToken($semPermissao->test_token)->getJson('/api/social-accounts/schedules');

        expect($response->status())->toBe(403)
            ->and($response->getContent())->not->toContain('Lançamento confidencial');
    });

    it('regressão: usuário de outro workspace não vê contas nem posts de /social-accounts/schedules', function () {
        Storage::fake('s3');
        $a = createUserWithPermissions(['social_accounts.view']);
        $contaA = connectedSocialAccount($a, 'youtube', ['nickname' => 'Canal do A']);
        $b = createUserWithPermissions(['social_accounts.view', 'posts.view']);

        $content = $this->withToken($b->test_token)->getJson('/api/social-accounts/schedules')->assertOk()->getContent();

        expect($content)->not->toContain('Canal do A')->and($content)->not->toContain($contaA->uuid);
    });

    it('VULN-TEN-02: usuário sem currentAccess (access_id nulo) não agenda em conta social de outro workspace', function () {
        Queue::fake();
        $vitima = createUserWithPermissions(['posts.create']);
        $contaVitima = connectedSocialAccount($vitima, 'tiktok', ['scopes' => TIKTOK_SCOPES_OK]);

        $atacante = createUserWithPermissions(['posts.create']);
        $midia = tiktokMedia($atacante); // arquivo do próprio workspace do atacante
        User::whereKey($atacante->id)->update(['access_id' => null]);

        $this->withToken($atacante->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $midia,
            'social_account_uuids' => [$contaVitima->uuid],
            'title' => 'spam',
            'privacy_level' => 'SELF_ONLY',
        ]);

        expect(ScheduledPost::withoutGlobalScopes()->where('social_account_id', $contaVitima->id)->count())->toBe(0);
    });

    it('regressão: membro com currentAccess no workspace B não agenda em conta do workspace A (mesmo sendo membro dos dois)', function () {
        Queue::fake();
        $u = createUserWithPermissions(['posts.create']);
        $wsA = Workspace::withoutGlobalScopes()->find($u->currentAccess->workspace_id);
        $contaA = connectedSocialAccount($u, 'tiktok', ['scopes' => TIKTOK_SCOPES_OK]);
        $wsB = Workspace::withoutGlobalScopes()->create(['name' => 'B', 'is_personal_team' => false]);
        $roleB = Role::withoutGlobalScopes()->create(['name' => 'rb', 'workspace_id' => $wsB->id]);
        $roleB->permissions()->sync([permission('posts.create')->id]);
        $accessB = Access::create(['user_id' => $u->id, 'role_id' => $roleB->id, 'workspace_id' => $wsB->id]);
        User::whereKey($u->id)->update(['access_id' => $accessB->id]);
        $midia = "workspaces/{$wsB->uuid}/videos/x.mp4";
        Storage::fake('s3');
        Storage::disk('s3')->put($midia, 'abc');

        $this->withToken($u->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $midia,
            'social_account_uuids' => [$contaA->uuid],
            'title' => 't',
            'privacy_level' => 'SELF_ONLY',
        ])->assertStatus(400); // ScheduledPostException::noAccountLinked

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('regressão: usuário de outro workspace não cancela nem reenvia post alheio (404)', function () {
        Queue::fake();
        $a = createUserWithPermissions(['posts.create', 'posts.delete']);
        $post = ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $a->id, 'social_account_id' => connectedSocialAccount($a, 'youtube')->id, 'platform' => 'youtube',
            'title' => 't', 'media_path' => 'workspaces/x/videos/a.mp4', 'status' => 'pending',
        ]);
        $b = createUserWithPermissions(['posts.create', 'posts.delete']);

        $this->withToken($b->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertNotFound();
        $this->withToken($b->test_token)->postJson("/api/scheduled-posts/{$post->uuid}/retry")->assertNotFound();
        expect($post->fresh()->status)->toBe('pending');
    });
});

describe('Esteira de publicação', function () {

    it('VULN-POST-01: cancelar um post não apaga a mídia que outro post pendente ainda vai publicar', function () {
        Queue::fake();
        Storage::fake('s3');
        $user = createUserWithPermissions(['posts.create', 'posts.delete']);
        $ws = $user->currentAccess->workspace->uuid;
        $path = "workspaces/{$ws}/videos/compartilhado.mp4";
        Storage::disk('s3')->put($path, 'video');
        $contaA = connectedSocialAccount($user, 'youtube');
        $contaB = connectedSocialAccount($user, 'youtube');

        // O agendamento em várias contas reaproveita o mesmo media_path (ScheduledPostService::schedule).
        $this->withToken($user->test_token)->postJson('/api/scheduled-posts', [
            'media_storage_path' => $path, 'social_account_uuids' => [$contaA->uuid, $contaB->uuid], 'title' => 'T',
        ])->assertCreated();
        $primeiro = ScheduledPost::withoutGlobalScopes()->where('social_account_id', $contaA->id)->sole();

        $this->withToken($user->test_token)->deleteJson("/api/scheduled-posts/{$primeiro->uuid}")->assertOk();

        expect(Storage::disk('s3')->exists($path))->toBeTrue();
    });

    it('regressão: PublishPostJob::failed não grava mensagem crua de exceção de terceiros nem em error_message nem na listagem', function () {
        Storage::fake('s3');
        $user = createUserWithPermissions(['posts.view']);
        $post = ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'social_account_id' => connectedSocialAccount($user, 'instagram')->id, 'platform' => 'instagram',
            'title' => 't', 'media_path' => null, 'status' => 'pending',
        ]);

        (new PublishPostJob($post))->failed(new Exception('cURL error for https://graph.instagram.com/x?access_token=SEGREDO-TOKEN-123'));

        $lista = $this->withToken($user->test_token)->getJson('/api/scheduled-posts')->assertOk()->getContent();
        expect($post->fresh()->error_message)->not->toContain('SEGREDO-TOKEN-123')->and($lista)->not->toContain('SEGREDO-TOKEN-123');
    });

    it('VULN-RETRY-04: PublishPostJob::failed não grava a mensagem crua da exceção de terceiros no log', function () {
        $user = createUserWithPermissions(['posts.view']);
        $post = ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'social_account_id' => connectedSocialAccount($user, 'instagram')->id, 'platform' => 'instagram',
            'title' => 't', 'media_path' => null, 'status' => 'pending',
        ]);
        $logs = [];
        Log::listen(function ($e) use (&$logs) {
            $logs[] = $e->message;
        });

        (new PublishPostJob($post))->failed(new Exception('cURL error for https://x.test/?access_token=SEGREDO-NO-LOG-555'));

        expect(implode("\n", $logs))->not->toContain('SEGREDO-NO-LOG-555');
    });

    it('regressão: erro do publish no Instagram (body da Meta com token) não vaza para error_message', function () {
        $user = createUserWithPermissions(['posts.view']);
        $account = connectedSocialAccount($user, 'instagram');
        $post = ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'social_account_id' => $account->id, 'platform' => 'instagram',
            'title' => 't', 'media_path' => 'workspaces/w/videos/a.mp4', 'status' => 'processing',
            'container_id' => 'c-1', 'container_created_at' => now(),
        ]);
        Http::fake([
            '*/media_publish' => Http::response(['error' => ['message' => 'Invalid OAuth access token SEGREDO-META-321', 'code' => 190]], 400),
            '*/c-1*' => Http::response(['status_code' => 'FINISHED']),
        ]);

        (new CheckInstagramContainerJob($post, $account, 'c-1'))->handle();

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')->and((string) $fresh->error_message)->not->toContain('SEGREDO-META-321');
    });

    it('regressão: erro do Google no upload do YouTube não grava o JSON cru em error_message', function () {
        $user = createUserWithPermissions(['posts.view']);
        $account = youtubeAccount($user);
        $path = youtubeStoredFile($user, 'v.mp4');
        $post = youtubePost($user, $account, $path);
        youtubeFakeSdk([youtubeErrorResponse(403, 'quotaExceeded', 'SEGREDO-GOOGLE-654 raw')]);

        $job = new PublishPostJob($post);
        try {
            $job->handle(app(SocialMediaFactory::class));
        } catch (Throwable $e) {
            $job->failed($e);
        }

        expect((string) $post->fresh()->error_message)->not->toContain('SEGREDO-GOOGLE-654');
    });

    it('VULN-RETRY-03b: permalink não string (array) da Graph API não causa TypeError e o post fecha como publicado', function () {
        $user = createUserWithPermissions(['posts.view']);
        $account = connectedSocialAccount($user, 'instagram');
        $post = ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'social_account_id' => $account->id, 'platform' => 'instagram',
            'title' => 'Reel', 'media_path' => 'workspaces/w/videos/a.mp4', 'status' => 'processing',
            'container_id' => 'container-1', 'container_created_at' => now(),
        ]);
        Storage::fake('s3');
        Http::fake([
            '*/container-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['id' => '179']),
            '*/179*' => Http::response(['permalink' => ['x' => 'y']]),
        ]);

        (new CheckInstagramContainerJob($post, $account, 'container-1'))->handle();

        expect($post->fresh()->status)->toBe('published');
    });
});

describe('Armazenamento e tokens', function () {

    it('VULN-SA-01: access_token e refresh_token não ficam em texto puro no banco', function () {
        $user = createUserWithPermissions([]);
        $conta = connectedSocialAccount($user, 'youtube', ['access_token' => 'TOKEN-PURO-ABC', 'refresh_token' => 'REFRESH-PURO-DEF']);

        $raw = DB::table('social_accounts')->where('id', $conta->id)->first();

        expect($raw->access_token)->not->toBe('TOKEN-PURO-ABC')->and($raw->refresh_token)->not->toBe('REFRESH-PURO-DEF');
    })->skip('VULN-SA-01: SocialAccount::$casts não usa "encrypted" para access_token/refresh_token/token_secret; um dump do banco entrega acesso às redes de todos os tenants.');

    it('VULN-UP-01: a presigned PUT de upload limita o tamanho (Content-Length assinado ou POST com content-length-range)', function () {
        config([
            'filesystems.disks.s3.key' => 'AKIATESTKEY', 'filesystems.disks.s3.secret' => 'secret',
            'filesystems.disks.s3.region' => 'us-east-1', 'filesystems.disks.s3.bucket' => 'b',
            'filesystems.disks.s3.url' => null, 'filesystems.disks.s3.endpoint' => null,
        ]);
        app('filesystem')->forgetDisk('s3');
        $user = createUserWithPermissions(['posts.create']);

        $json = $this->withToken($user->test_token)->postJson('/api/scheduled-posts/upload-url', [
            'directory' => 'videos', 'content_type' => 'video/mp4',
        ])->assertOk()->json();

        parse_str(parse_url($json['url'], PHP_URL_QUERY), $q);
        expect(strtolower($q['X-Amz-SignedHeaders'] ?? ''))->toContain('content-length');
    })->skip('VULN-UP-01: a presigned PUT assina só o header host (X-Amz-SignedHeaders=host): nem content-type nem tamanho são impostos pelo S3; max_size é informativo e directory não casa com content_type.');
});
