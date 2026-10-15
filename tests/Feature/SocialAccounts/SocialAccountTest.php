<?php

use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Http::fake();
});

function socialAccountFor(User $user, array $overrides = []): SocialAccount
{
    return SocialAccount::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'workspace_id' => $user->currentAccess->workspace_id,
        'platform' => 'youtube',
        'platform_id' => 'canal-'.uniqid(),
        'access_token' => 'token-falso',
        'nickname' => 'Meu canal',
    ], $overrides));
}

// ---------------------------------------------------------------------------
// LISTAR
// ---------------------------------------------------------------------------

describe('listar contas sociais', function () {

    it('lista as contas do workspace sem expor tokens', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        socialAccountFor($user);

        $response = $this->withToken($user->test_token)
            ->getJson('/api/social-accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nickname', 'Meu canal');

        expect($response->getContent())
            ->not->toContain('token-falso')
            ->not->toContain('access_token')
            ->not->toContain('refresh_token');
    });

    it('não lista contas de outros workspaces', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        socialAccountFor(createUserWithPermissions([]));

        $this->withToken($user->test_token)
            ->getJson('/api/social-accounts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('retorna 403 sem a permissão social_accounts.view', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)
            ->getJson('/api/social-accounts')
            ->assertForbidden();
    });

    it('retorna 401 quando não está autenticado', function () {
        $this->getJson('/api/social-accounts')->assertUnauthorized();
    });

});

// ---------------------------------------------------------------------------
// DESCONECTAR
// ---------------------------------------------------------------------------

describe('desconectar conta social', function () {

    it('remove a conta, revoga o token no Google e cancela os posts pendentes', function () {
        $user = createUserWithPermissions(['social_accounts.disconnect']);
        $account = socialAccountFor($user);

        $post = ScheduledPost::create([
            'user_id' => $user->id,
            'social_account_id' => $account->id,
            'platform' => 'youtube',
            'media_path' => 'workspaces/x/videos/a.mp4',
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this->withToken($user->test_token)
            ->deleteJson("/api/social-accounts/{$account->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('social_accounts', ['id' => $account->id]);
        expect($post->fresh()->status)->toBe('cancelled');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/revoke'));
    });

    it('retorna 404 ao desconectar conta de outro workspace', function () {
        $user = createUserWithPermissions(['social_accounts.disconnect']);
        $foreign = socialAccountFor(createUserWithPermissions([]));

        $this->withToken($user->test_token)
            ->deleteJson("/api/social-accounts/{$foreign->uuid}")
            ->assertNotFound();

        $this->assertDatabaseHas('social_accounts', ['id' => $foreign->id]);
        Http::assertNothingSent();
    });

    it('retorna 403 sem a permissão social_accounts.disconnect', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $account = socialAccountFor($user);

        $this->withToken($user->test_token)
            ->deleteJson("/api/social-accounts/{$account->uuid}")
            ->assertForbidden();

        $this->assertDatabaseHas('social_accounts', ['id' => $account->id]);
    });

});

// ---------------------------------------------------------------------------
// CALLBACK OAUTH (público)
// ---------------------------------------------------------------------------

describe('callback oauth', function () {

    it('redireciona com erro quando o state está ausente', function () {
        $this->get('/api/social-accounts/youtube/callback?code=abc')
            ->assertRedirectContains('error=');
    });

    it('redireciona com erro quando o state foi adulterado', function () {
        $this->get('/api/social-accounts/youtube/callback?code=abc&state=nao-criptografado')
            ->assertRedirectContains('error=');
    });

    it('redireciona com erro quando o workspace do state não existe', function () {
        $state = encrypt('00000000-0000-0000-0000-000000000000');

        $this->get('/api/social-accounts/youtube/callback?code=abc&state='.urlencode($state))
            ->assertRedirectContains('error=');

        Http::assertNothingSent();
    });

});

// ---------------------------------------------------------------------------
// ANALYTICS — isolamento
// ---------------------------------------------------------------------------

describe('analytics de conta social', function () {

    it('retorna 404 para conta de outro workspace', function () {
        $user = createUserWithPermissions(['social_accounts.view']);
        $foreign = socialAccountFor(createUserWithPermissions([]));

        $this->withToken($user->test_token)
            ->getJson("/api/analytics/{$foreign->uuid}/dashboard")
            ->assertNotFound();

        Http::assertNothingSent();
    });

    // Analytics por plataforma (inclusive TikTok, que antes respondia 400): tests/Feature/Analytics/.

});
