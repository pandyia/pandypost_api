<?php

use App\Jobs\PublishPostJob;
use App\Models\Access;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Factories\OAuthProviderFactory;
use App\Services\TikTokService;
use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function signupUser(array $overrides = []): array
{
    $data = array_merge([
        'name' => 'Teste User',
        'email' => 'teste@gmail.com',
        'password' => '12345678G',
        'password_confirmation' => '12345678G',
    ], $overrides);

    $response = test()->postJson('/api/auth/signup', $data);

    return [
        'response' => $response,
        'user' => User::where('email', $data['email'])->first(),
        'token' => $response->json('token'),
    ];
}

function loginAs(User $user, string $password = 'password'): string
{
    $response = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    return $response->json('token');
}

// ---------------------------------------------------------------------------
// Helpers compartilhados entre testes
// ---------------------------------------------------------------------------

function permission(string $name): Permission
{
    return Permission::firstOrCreate(
        ['name' => $name],
        ['name' => $name, 'description' => $name]
    );
}

/**
 * Cria um usuário verificado com workspace, role e permissões.
 */
function createUserWithPermissions(array $permissionNames = [], bool $isPersonalTeam = true, ?Workspace $workspace = null): User
{
    $user = User::factory()->create();

    $workspace ??= Workspace::withoutGlobalScopes()->create([
        'name' => 'Test Workspace',
        'is_personal_team' => $isPersonalTeam,
    ]);

    $role = Role::withoutGlobalScopes()->create([
        'name' => 'Test Role '.uniqid(),
        'workspace_id' => $workspace->id,
    ]);

    if (! empty($permissionNames)) {
        foreach ($permissionNames as $name) {
            permission($name);
        }
        $ids = Permission::whereIn('name', $permissionNames)->pluck('id');
        $role->permissions()->sync($ids);
    }

    $access = Access::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'workspace_id' => $workspace->id,
    ]);

    $user->update(['access_id' => $access->id]);
    $user->setRelation('currentAccess', $access);
    $user->test_token = $user->createToken('test')->plainTextToken;

    return $user;
}

function createUserWithoutPermissions(): User
{
    return createUserWithPermissions([]);
}

/**
 * Adiciona um usuário a um workspace existente.
 */
function addUserToWorkspace(Workspace $workspace, ?User $user = null): User
{
    $user ??= User::factory()->create();

    $role = Role::withoutGlobalScopes()->create([
        'name' => 'Member Role '.uniqid(),
        'workspace_id' => $workspace->id,
    ]);

    $access = Access::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'workspace_id' => $workspace->id,
    ]);

    $user->update(['access_id' => $access->id]);

    return $user;
}

/**
 * Cria um super admin (equipe do Pandypost) — único com acesso a /admin/**.
 */
function createSuperAdmin(): User
{
    $user = createUserWithPermissions([]);
    User::whereKey($user->id)->update(['is_super_admin' => true]);
    $user->is_super_admin = true;

    return $user;
}

/**
 * Conta social conectada no workspace corrente do usuário, com token válido.
 */
function connectedSocialAccount(User $user, string $platform, array $overrides = []): SocialAccount
{
    return SocialAccount::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'workspace_id' => $user->currentAccess->workspace_id,
        'platform' => $platform,
        'platform_id' => "{$platform}-".uniqid(),
        'access_token' => 'token-valido',
        'refresh_token' => 'refresh-valido',
        'expires_at' => now()->addDay(),
        'nickname' => 'Conta de teste',
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Helpers da spec 011 (publicação no TikTok). Sempre com Http::fake; nunca a API real.
// ---------------------------------------------------------------------------

const TIKTOK_SCOPES_OK = 'user.info.basic,user.info.profile,user.info.stats,video.list,video.upload,video.publish';
const TIKTOK_UPLOAD_URL = 'https://open-upload.tiktokapis.com/video/abc';

/** Conta TikTok conectada, por padrão com o escopo de publicação concedido. */
function tiktokAccount(User $user, array $overrides = []): SocialAccount
{
    return connectedSocialAccount($user, 'tiktok', array_merge([
        'platform_id' => 'open-id-'.uniqid(),
        'username' => 'canal_teste',
        'scopes' => TIKTOK_SCOPES_OK,
    ], $overrides));
}

/** Grava um "vídeo" no S3 fake do workspace do usuário e devolve o path. */
function tiktokMedia(User $user, int $bytes = 23, string $name = 'test.mp4'): string
{
    $path = "workspaces/{$user->currentAccess->workspace->uuid}/videos/{$name}";
    Storage::fake('s3');
    Storage::disk('s3')->put($path, str_repeat('a', $bytes));

    return $path;
}

function tiktokPost(User $user, SocialAccount $account, string $mediaPath, array $payload = [], array $overrides = []): ScheduledPost
{
    return ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => 'tiktok',
        'title' => 'Meu vídeo',
        'caption' => 'Olha esse vídeo!',
        'media_path' => $mediaPath,
        'payload' => array_merge(['privacy_level' => 'SELF_ONLY'], $payload),
        'status' => 'pending',
    ], $overrides));
}

/**
 * Fake de todos os endpoints do TikTok usados na publicação.
 * Chaves: creator (sobrescreve data do creator_info), creator_response, init_response, put_status, put_body, status_data.
 */
function tiktokFakeApi(array $o = []): void
{
    $creator = array_merge([
        'creator_avatar_url' => 'https://p16.tiktokcdn.test/avatar.jpg',
        'creator_username' => 'canal_teste',
        'creator_nickname' => 'Canal Teste',
        'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'],
        'comment_disabled' => false,
        'duet_disabled' => false,
        'stitch_disabled' => false,
        'max_video_post_duration_sec' => 600,
    ], $o['creator'] ?? []);

    Http::fake([
        'open.tiktokapis.com/v2/post/publish/creator_info/query/*' => $o['creator_response']
            ?? Http::response(['data' => $creator, 'error' => ['code' => 'ok', 'message' => '', 'log_id' => 'log1']]),
        'open.tiktokapis.com/v2/post/publish/video/init/*' => $o['init_response']
            ?? Http::response([
                'data' => ['publish_id' => 'v_pub_file~v2.123', 'upload_url' => TIKTOK_UPLOAD_URL],
                'error' => ['code' => 'ok', 'message' => '', 'log_id' => 'log2'],
            ]),
        'open.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response([
            'data' => $o['status_data'] ?? ['status' => 'PUBLISH_COMPLETE'],
            'error' => ['code' => 'ok', 'message' => '', 'log_id' => 'log3'],
        ]),
        'open.tiktokapis.com/v2/oauth/token/*' => $o['token_response']
            ?? Http::response(['access_token' => 'novo-access-token', 'refresh_token' => 'novo-refresh', 'expires_in' => 86400]),
        'open-upload.tiktokapis.com/*' => function ($request) use ($o) {
            if (isset($o['put_status'])) {
                return Http::response($o['put_body'] ?? '', $o['put_status']);
            }
            // 206 = chunk parcial aceito; 201 = último chunk (upload completo).
            preg_match('#bytes (\d+)-(\d+)/(\d+)#', $request->header('Content-Range')[0] ?? '', $m);

            return Http::response('', ($m && (int) $m[2] === (int) $m[3] - 1) ? 201 : 206);
        },
    ]);
    Http::preventStrayRequests();
}

/**
 * Executa o upload como a esteira real: o service só propaga a TikTokException e quem grava `failed`
 * é o PublishPostJob::failed() (Decisão TDD 4 da spec 011). Relança para o teste afirmar a exceção.
 */
function tiktokUploadViaJob(SocialAccount $account, ScheduledPost $post): void
{
    try {
        app(TikTokService::class)->upload($account, $post);
    } catch (Throwable $e) {
        (new PublishPostJob($post))->failed($e);

        throw $e;
    }
}

/** Registra os logs da execução em memória e devolve um closure que devolve tudo como texto. */
function tiktokCaptureLogs(): Closure
{
    $handler = new TestHandler;
    config(['logging.default' => 'tiktok_capture', 'logging.channels.tiktok_capture' => [
        'driver' => 'monolog',
        'handler' => TestHandler::class,
    ]]);
    Log::forgetChannel('tiktok_capture');
    $handler = Log::channel('tiktok_capture')->getLogger()->getHandlers()[0];

    return fn (): string => collect($handler->getRecords())
        ->map(fn ($r) => $r->message.' '.json_encode($r->context))
        ->implode("\n");
}

// ---------------------------------------------------------------------------
// Helpers da spec 016 (publicação no YouTube). O SDK do Google roda sobre um Guzzle MockHandler; nunca a API real.
// ---------------------------------------------------------------------------

/**
 * Troca o Google\Client do container por um cujo HTTP é a fila de respostas informada.
 * Devolve o histórico das requisições enviadas (['request' => RequestInterface]).
 */
function youtubeFakeSdk(array $responses): ArrayObject
{
    $history = new ArrayObject;
    // O histórico fica no handler final (depois do middleware de autenticação do SDK) para enxergar o header Authorization.
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create(function ($request, array $options) use ($mock, $history) {
        $history[] = ['request' => $request];

        return $mock($request, $options);
    });

    app()->bind(GoogleClient::class, function () use ($stack) {
        $client = new GoogleClient;
        $client->setHttpClient(new GuzzleClient(['handler' => $stack, 'base_uri' => 'https://www.googleapis.com', 'http_errors' => false]));

        return $client;
    });

    return $history;
}

/** Resposta do endpoint de token do Google à renovação feita pelo SDK. */
function youtubeRefreshResponse(string $accessToken = 'ya29.renovado'): PsrResponse
{
    return new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => $accessToken, 'expires_in' => 3600]));
}

/** Início do resumable upload: o Google devolve a URI de envio no header Location. */
function youtubeResumeResponse(): PsrResponse
{
    return new PsrResponse(200, ['Location' => 'https://upload.youtube.test/resumable/abc']);
}

/** Resposta final de um envio (videos.insert devolve o recurso com `id`). */
function youtubeDoneResponse(string $id = 'yt-video-1'): PsrResponse
{
    return new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => $id]));
}

/** Resposta de erro da API do Google no formato real (`error.errors[].reason`). */
function youtubeErrorResponse(int $status, ?string $reason, string $message = 'Mensagem crua do Google'): PsrResponse
{
    return new PsrResponse($status, ['Content-Type' => 'application/json'], json_encode([
        'error' => [
            'code' => $status,
            'message' => $message,
            'errors' => $reason ? [['domain' => 'youtube.quota', 'reason' => $reason, 'message' => $message]] : [],
        ],
    ]));
}

/** Conta YouTube conectada como a conexão grava hoje: access_token é a string do Socialite. */
function youtubeAccount(User $user, array $overrides = []): SocialAccount
{
    return connectedSocialAccount($user, 'youtube', array_merge(['access_token' => 'ya29.token-valido'], $overrides));
}

/** Grava um arquivo no S3 fake do workspace do usuário e devolve o path. */
function youtubeStoredFile(User $user, string $name, int $bytes = 100): string
{
    $path = "workspaces/{$user->currentAccess->workspace->uuid}/videos/{$name}";
    Storage::disk('s3')->put($path, str_repeat('a', $bytes));

    return $path;
}

function youtubePost(User $user, SocialAccount $account, string $mediaPath, array $payload = [], array $overrides = []): ScheduledPost
{
    return ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => 'youtube',
        'title' => 'Meu vídeo',
        'caption' => 'Descrição do vídeo',
        'media_path' => $mediaPath,
        'payload' => $payload,
        'status' => 'pending',
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Helpers da spec 017 (conta social do workspace + OAuth state seguro)
// ---------------------------------------------------------------------------

/** Emite o state pelo fluxo real (provider::getRedirectUrl) para o usuário iniciador. Chame ANTES de mockar o Socialite. */
function oauthStateFor(User $user, string $factoryKey): string
{
    config([
        'services.google.client_id' => 'google-id',
        'services.google.client_secret' => 'google-secret',
        'services.google.redirect' => 'https://api.test/api/social-accounts/google/callback',
        'services.meta.app_id' => 'meta-id',
        'services.meta.app_secret' => 'meta-secret',
        'services.meta.redirect' => 'https://api.test/api/social-accounts/instagram/callback',
        'services.tiktok.client_key' => 'tt-key',
        'services.tiktok.client_secret' => 'tt-secret',
        'services.tiktok.redirect' => 'https://api.test/api/social-accounts/tiktok/callback',
    ]);

    $url = app(OAuthProviderFactory::class)->make($factoryKey)->getRedirectUrl($user);
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    return $query['state'];
}

/** Abre o state (cifrado pelo app) e devolve [payload, modo] para permitir adulterar e re-selar. */
function oauthStateOpen(string $state): array
{
    try {
        $result = [decrypt($state), 'serialized'];
    } catch (Throwable) {
        $result = [json_decode(Crypt::decryptString($state), true), 'json'];
    }

    if (! is_array($result[0])) {
        throw new RuntimeException('state sem payload estruturado (esperado: workspace_uuid, user_id, nonce, expiração)');
    }

    return $result;
}

/** Re-sela um payload (adulterado) com a chave do app, no mesmo formato do state original. */
function oauthStateSeal(array $payload, string $mode): string
{
    return $mode === 'json'
        ? Crypt::encryptString(json_encode($payload))
        : encrypt($payload);
}
