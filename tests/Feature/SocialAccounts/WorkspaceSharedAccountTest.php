<?php

use App\Contracts\SocialMediaServiceInterface;
use App\Enums\Platform;
use App\Exceptions\ScheduledPostException;
use App\Jobs\PublishPostJob;
use App\Models\Access;
use App\Models\Role;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Factories\SocialMediaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// Spec 017: a conta social pertence ao workspace; quem tem acesso e permissão agenda nela,
// independente de quem a conectou ou reconectou.

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['app.frontend_url' => 'https://front.test']);
    Queue::fake();
    $this->a = createUserWithPermissions(['posts.create', 'posts.view', 'social_accounts.view', 'social_accounts.connect']);
    $this->workspace = Workspace::withoutGlobalScopes()->find($this->a->currentAccess->workspace_id);
    // Membro B do mesmo workspace; a conta foi conectada pelo A (user_id = A).
    $this->b = createUserWithPermissions(['posts.create', 'posts.view', 'social_accounts.view', 'social_accounts.connect'], true, $this->workspace);
    $this->conta = tiktokAccount($this->a);
    $this->media = tiktokMedia($this->a);
});

function sharedBody(array $extra = []): array
{
    return array_merge([
        'media_storage_path' => test()->media,
        'social_account_uuids' => [test()->conta->uuid],
        'title' => 'Meu TikTok',
        'caption' => 'Legenda',
        'privacy_level' => 'SELF_ONLY',
    ], $extra);
}

function sharedPostCount(): int
{
    return ScheduledPost::withoutGlobalScopes()->count();
}

/** Factory mockada que espera (ou não) o upload; devolve o mock do serviço da rede. */
function sharedFactory(bool $expectUpload): SocialMediaFactory
{
    $factory = Mockery::mock(SocialMediaFactory::class);
    if ($expectUpload) {
        $service = Mockery::mock(SocialMediaServiceInterface::class);
        $service->shouldReceive('upload')->once();
        $factory->shouldReceive('make')->once()->with(Platform::TIKTOK)->andReturn($service);
    } else {
        $factory->shouldReceive('make')->andReturnUsing(fn () => throw new LogicException('publicou na rede apesar de a conta ser de outro workspace'));
    }

    return $factory;
}

describe('RF-1: agendar em conta conectada por outro membro do workspace', function () {

    it('201: B (posts.create) agenda na conta conectada por A; o post fica com user_id do B e a fila é acionada', function () {
        $this->withToken($this->b->test_token)->postJson('/api/scheduled-posts', sharedBody())->assertCreated();

        $post = ScheduledPost::withoutGlobalScopes()->sole();
        expect($post->user_id)->toBe($this->b->id)
            ->and($post->social_account_id)->toBe($this->conta->id)
            ->and($post->platform->value)->toBe('tiktok')
            ->and($post->status)->toBe('pending')
            ->and($this->conta->fresh()->user_id)->toBe($this->a->id);
        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('o próprio A que conectou continua podendo agendar', function () {
        $this->withToken($this->a->test_token)->postJson('/api/scheduled-posts', sharedBody())->assertCreated();

        expect(ScheduledPost::withoutGlobalScopes()->sole()->user_id)->toBe($this->a->id);
    });

    it('403: B sem posts.create não agenda, mesmo sendo do workspace da conta', function () {
        $semPermissao = createUserWithPermissions(['posts.view'], true, $this->workspace);

        $this->withToken($semPermissao->test_token)->postJson('/api/scheduled-posts', sharedBody())->assertForbidden();

        expect(sharedPostCount())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('401: sem autenticação', function () {
        $this->postJson('/api/scheduled-posts', sharedBody())->assertUnauthorized();

        expect(sharedPostCount())->toBe(0);
    });

    it('422: sem social_account_uuids nada é criado', function () {
        $this->withToken($this->b->test_token)->postJson('/api/scheduled-posts', sharedBody(['social_account_uuids' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['social_account_uuids']);

        expect(sharedPostCount())->toBe(0);
    });

    it('conta de OUTRO workspace: mesmo erro de hoje (no_account_linked, 400), nada criado e nada enfileirado', function () {
        $estranho = createUserWithPermissions(['posts.create']);
        $contaAlheia = tiktokAccount($estranho);

        $this->withToken($this->b->test_token)->postJson('/api/scheduled-posts', sharedBody(['social_account_uuids' => [$contaAlheia->uuid]]))
            ->assertStatus(400)
            ->assertJsonPath('error', 'no_account_linked');

        expect(sharedPostCount())->toBe(0)
            ->and(SocialAccount::withoutGlobalScopes()->count())->toBe(2);
        Queue::assertNothingPushed();
    });

    it('várias contas, uma de outro workspace: tudo ou nada (erro no_account_linked e nenhum post criado)', function () {
        $estranho = createUserWithPermissions(['posts.create']);
        $contaAlheia = tiktokAccount($estranho);
        $contaB = tiktokAccount($this->b);

        $this->withToken($this->b->test_token)->postJson('/api/scheduled-posts', sharedBody(['social_account_uuids' => [$this->conta->uuid, $contaB->uuid, $contaAlheia->uuid]]))
            ->assertStatus(400)
            ->assertJsonPath('error', 'no_account_linked');

        expect(sharedPostCount())->toBe(0);
    });

    it('conta de outro workspace cujo user_id é o do próprio B continua recusada (o que vale é o workspace, não o user_id)', function () {
        $estranho = createUserWithPermissions(['posts.create']);
        $contaAlheia = tiktokAccount($estranho, ['user_id' => $this->b->id]);

        $this->withToken($this->b->test_token)->postJson('/api/scheduled-posts', sharedBody(['social_account_uuids' => [$contaAlheia->uuid]]))
            ->assertStatus(400)
            ->assertJsonPath('error', 'no_account_linked');

        expect(sharedPostCount())->toBe(0);
    });

});

describe('RF-2: PublishPostJob valida o workspace, não o user_id', function () {

    it('publica quando conta e post são do mesmo workspace, mesmo com user_id diferentes (post do B, conta do A)', function () {
        $post = tiktokPost($this->b, $this->conta, $this->media);
        expect($post->user_id)->not->toBe($this->conta->user_id);

        (new PublishPostJob($post))->handle(sharedFactory(true));
    });

    it('falha com account_not_found quando a conta é de outro workspace, mesmo que o user_id da conta seja o do autor do post', function () {
        $estranho = createUserWithPermissions(['posts.create']);
        $contaAlheia = tiktokAccount($estranho, ['user_id' => $this->b->id]);
        $post = tiktokPost($this->b, $contaAlheia, $this->media);

        try {
            (new PublishPostJob($post))->handle(sharedFactory(false));
            $this->fail('deveria lançar ScheduledPostException');
        } catch (ScheduledPostException $e) {
            expect($e->getErrorCode())->toBe('account_not_found')->and($e->getHttpCode())->toBe(404);
        }
    });

    it('publica mesmo que o autor tenha trocado de workspace depois de agendar (acesso ao workspace da conta basta, não o currentAccess)', function () {
        $post = tiktokPost($this->b, $this->conta, $this->media);
        $outroWorkspace = createUserWithPermissions([])->currentAccess->workspace;
        $acessoNovo = Access::create([
            'user_id' => $this->b->id,
            'role_id' => Role::withoutGlobalScopes()->where('workspace_id', $outroWorkspace->id)->value('id'),
            'workspace_id' => $outroWorkspace->id,
        ]);
        User::whereKey($this->b->id)->update(['access_id' => $acessoNovo->id]);
        expect($this->b->fresh()->currentAccess->workspace_id)->not->toBe($this->conta->workspace_id);

        (new PublishPostJob($post->fresh()))->handle(sharedFactory(true));
    });

    it('falha com account_not_found quando o autor saiu do workspace da conta (sem Access), sem publicar', function () {
        $post = tiktokPost($this->b, $this->conta, $this->media);
        Access::where('user_id', $this->b->id)->where('workspace_id', $this->workspace->id)->delete();

        expect(fn () => (new PublishPostJob($post->fresh()))->handle(sharedFactory(false)))
            ->toThrow(ScheduledPostException::class);
    });

    it('falha com account_not_found quando a conta é de outro workspace e de outro usuário', function () {
        $estranho = createUserWithPermissions(['posts.create']);
        $post = tiktokPost($this->b, tiktokAccount($estranho), $this->media);

        expect(fn () => (new PublishPostJob($post))->handle(sharedFactory(false)))
            ->toThrow(ScheduledPostException::class);
    });

});

describe('RF-3: reconectar por outro membro não quebra posts pendentes', function () {

    it('B reconecta a conta do A: a conta é atualizada (sem duplicar), user_id vira B e o post pendente do A continua publicável', function () {
        $postDoA = tiktokPost($this->a, $this->conta, $this->media);
        $state = oauthStateFor($this->b, 'tiktok');
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response(['access_token' => 'novo-token', 'refresh_token' => 'novo-refresh', 'open_id' => $this->conta->platform_id, 'expires_in' => 86400, 'scope' => TIKTOK_SCOPES_OK]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['display_name' => 'Canal']], 'error' => ['code' => 'ok']]),
        ]);

        $this->get('/api/social-accounts/tiktok/callback?code=c&state='.urlencode($state))->assertRedirectContains('success=1');

        $conta = SocialAccount::withoutGlobalScopes()->where('platform', 'tiktok')->sole();
        expect($conta->id)->toBe($this->conta->id)
            ->and($conta->user_id)->toBe($this->b->id)
            ->and($conta->access_token)->toBe('novo-token')
            ->and($postDoA->fresh()->status)->toBe('pending')
            ->and($postDoA->fresh()->social_account_id)->toBe($conta->id);

        (new PublishPostJob($postDoA->fresh()))->handle(sharedFactory(true));
    });

    it('depois da reconexão pelo B, o A ainda consegue agendar na mesma conta', function () {
        SocialAccount::withoutGlobalScopes()->whereKey($this->conta->id)->update(['user_id' => $this->b->id]);

        $this->withToken($this->a->test_token)->postJson('/api/scheduled-posts', sharedBody())->assertCreated();

        expect(ScheduledPost::withoutGlobalScopes()->sole()->user_id)->toBe($this->a->id);
    });

});

describe('RF-6: listagem e uso das contas escopados ao workspace, sem filtro por user_id', function () {

    it('index: B vê a conta conectada pelo A', function () {
        $this->withToken($this->b->test_token)->getJson('/api/social-accounts')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $this->conta->uuid);
    });

    it('index: quem é de outro workspace não vê a conta', function () {
        $estranho = createUserWithPermissions(['social_accounts.view']);

        $this->withToken($estranho->test_token)->getJson('/api/social-accounts')
            ->assertOk()->assertJsonCount(0, 'data');
    });

    it('index: 403 sem social_accounts.view', function () {
        $semPermissao = createUserWithPermissions([], true, $this->workspace);

        $this->withToken($semPermissao->test_token)->getJson('/api/social-accounts')->assertForbidden();
    });

    it('index: 401 sem token', function () {
        $this->getJson('/api/social-accounts')->assertUnauthorized();
    });

    it('schedules: B vê a conta do A com os agendamentos de todos os membros', function () {
        tiktokPost($this->a, $this->conta, $this->media);
        tiktokPost($this->b, $this->conta, $this->media);

        $this->withToken($this->b->test_token)->getJson('/api/social-accounts/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.scheduled_posts');
    });

    it('creator-info: B consulta a conta do A', function () {
        tiktokFakeApi();

        $this->withToken($this->b->test_token)->getJson("/api/social-accounts/{$this->conta->uuid}/tiktok/creator-info")->assertOk();
    });

    it('creator-info: outro workspace recebe 404', function () {
        $estranho = createUserWithPermissions(['social_accounts.view']);
        tiktokFakeApi();

        $this->withToken($estranho->test_token)->getJson("/api/social-accounts/{$this->conta->uuid}/tiktok/creator-info")->assertNotFound();
    });

    it('analytics: B consulta best-times da conta do A', function () {
        Http::fake(['open.tiktokapis.com/v2/video/list/*' => Http::response(['data' => ['videos' => [], 'has_more' => false], 'error' => ['code' => 'ok']])]);

        $this->withToken($this->b->test_token)->getJson("/api/analytics/{$this->conta->uuid}/best-times")->assertOk();
    });

    it('analytics: outro workspace recebe 404', function () {
        $estranho = createUserWithPermissions([]);
        Http::fake(['open.tiktokapis.com/v2/video/list/*' => Http::response(['data' => ['videos' => [], 'has_more' => false], 'error' => ['code' => 'ok']])]);

        $this->withToken($estranho->test_token)->getJson("/api/analytics/{$this->conta->uuid}/best-times")->assertNotFound();
    });

    it('desconectar: B (social_accounts.disconnect) desconecta a conta do A e os pendentes viram cancelled', function () {
        $disconnect = createUserWithPermissions(['social_accounts.disconnect'], true, $this->workspace);
        $post = tiktokPost($this->a, $this->conta, $this->media);

        $this->withToken($disconnect->test_token)->deleteJson("/api/social-accounts/{$this->conta->uuid}")->assertOk();

        expect(SocialAccount::withoutGlobalScopes()->find($this->conta->id)?->trashed() ?? true)->toBeTrue()
            ->and($post->fresh()->status)->toBe('cancelled');
    });

});
