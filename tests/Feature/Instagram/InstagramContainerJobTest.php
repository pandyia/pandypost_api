<?php

use App\Enums\Exceptions\InstagramError;
use App\Exceptions\InstagramException;
use App\Jobs\CheckInstagramContainerJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Http::preventStrayRequests();
});

function igJobPost(array $overrides = []): array
{
    $user = createUserWithPermissions(['posts.create']);
    $account = connectedSocialAccount($user, 'instagram', ['platform_id' => 'ig-conta-1']);
    Storage::disk('s3')->put('workspaces/w/videos/j.mp4', 'midia');

    $post = ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => $account->id,
        'platform' => 'instagram',
        'title' => 'Reel',
        'media_path' => 'workspaces/w/videos/j.mp4',
        'status' => 'processing',
        'container_id' => 'cj-1',
        'container_created_at' => now(),
    ], $overrides));

    return [$account, $post];
}

function igRunJob(ScheduledPost $post, SocialAccount $account, bool $shouldPublish = true, int $attempts = 1): CheckInstagramContainerJob
{
    $job = new CheckInstagramContainerJob($post, $account, 'cj-1', $shouldPublish);
    $job->withFakeQueueInteractions();
    $job->job->attempts = $attempts;
    $job->handle();

    return $job;
}

describe('CheckInstagramContainerJob', function () {

    it('container em andamento reagenda com o backoff [2,4,8,16,30]', function (int $attempt, int $delay) {
        [$account, $post] = igJobPost();
        Http::fake(['*/cj-1*' => Http::response(['status_code' => 'IN_PROGRESS'])]);

        $job = igRunJob($post, $account, true, $attempt);

        $job->assertReleased($delay);
        expect($post->fresh()->status)->toBe('processing');
    })->with([[1, 2], [2, 4], [3, 8], [4, 16], [5, 30], [9, 30]]);

    it('FINISHED com shouldPublish=false não publica (warmup completo)', function () {
        [$account, $post] = igJobPost();
        Http::fake(['*/cj-1*' => Http::response(['status_code' => 'FINISHED'])]);

        igRunJob($post, $account, false);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'media_publish'));
        expect($post->fresh()->status)->toBe('processing');
    });

    it('FINISHED publica, grava o post e apaga a mídia do S3 (Apontamento 1 da spec 015)', function () {
        [$account, $post] = igJobPost();
        Http::fake([
            '*/cj-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/ig-conta-1/media_publish' => Http::response(['id' => 'midia-1']),
            '*/midia-1*' => Http::response(['permalink' => 'https://www.instagram.com/p/AAA/']),
        ]);

        igRunJob($post, $account);

        expect($post->fresh()->status)->toBe('published');
        Storage::disk('s3')->assertMissing($post->media_path);
    });

    it('erro no media_publish deixa o post failed com mensagem não vazia', function () {
        [$account, $post] = igJobPost();
        Http::fake([
            '*/cj-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/ig-conta-1/media_publish' => Http::response(['error' => ['code' => 100, 'message' => 'Invalid']], 400),
        ]);

        igRunJob($post, $account);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')->and($fresh->error_message)->not->toBeEmpty();
        Storage::disk('s3')->assertExists($post->media_path);
    });

    it('failed() marca failed por tempo esgotado quando o post ainda está em processing', function () {
        [$account, $post] = igJobPost();

        (new CheckInstagramContainerJob($post, $account, 'cj-1'))->failed(new RuntimeException('tentativas esgotadas'));

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->toBe(InstagramException::publishTimeout()->getMessage())
            ->and($fresh->container_id)->toBe('cj-1')
            ->and($fresh->failed_at)->not->toBeNull();
        Storage::disk('s3')->assertExists($post->media_path);
    });

    it('failed() usa a mensagem da exceção de domínio e nunca a de terceiros', function () {
        [$account, $post] = igJobPost();

        (new CheckInstagramContainerJob($post, $account, 'cj-1'))->failed(InstagramException::fromApiCode(190));

        expect($post->fresh()->error_message)->toBe(InstagramError::ACCESS_DENIED->message());
    });

    it('failed() não mexe em post que já saiu de processing', function () {
        [$account, $post] = igJobPost(['status' => 'published']);

        (new CheckInstagramContainerJob($post, $account, 'cj-1'))->failed(new RuntimeException('x'));

        expect($post->fresh()->status)->toBe('published');
    });

    it('container ERROR marca failed sem falhar o job na fila (sem failed_jobs duplicado)', function () {
        [$account, $post] = igJobPost();
        Http::fake(['*/cj-1*' => Http::response(['status_code' => 'ERROR'])]);

        $job = igRunJob($post, $account);

        $job->assertNotFailed();
        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->container_id)->toBeNull()
            ->and($fresh->error_message)->toBe(InstagramError::CONTAINER_FAILED->message());
    });

    it('erro de token ao consultar o container falha na hora, sem gastar as 15 tentativas', function () {
        [$account, $post] = igJobPost();
        Http::fake(['*/cj-1*' => Http::response(['error' => ['code' => 190, 'message' => 'Token SEGREDO']], 401)]);

        $job = igRunJob($post, $account);

        $job->assertNotReleased();
        expect($post->fresh()->status)->toBe('failed')
            ->and($post->fresh()->error_message)->toBe(InstagramError::ACCESS_DENIED->message());
    });

    it('instabilidade da Meta ao consultar o container propaga para o retry da fila', function () {
        [$account, $post] = igJobPost();
        Http::fake(['*/cj-1*' => Http::response(['error' => ['code' => 2, 'message' => 'x']], 500)]);

        expect(fn () => igRunJob($post, $account))->toThrow(InstagramException::class);
        expect($post->fresh()->status)->toBe('processing');
    });

    it('media_publish sem conexão propaga sem marcar failed e sem vazar o token', function () {
        Log::spy();
        [$account, $post] = igJobPost();
        Http::fake([
            '*/cj-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => fn () => throw new ConnectionException('timeout para https://graph.instagram.com/x?access_token=SEGREDO-IG'),
        ]);

        expect(fn () => igRunJob($post, $account))->toThrow(InstagramException::class, InstagramError::API_UNAVAILABLE->message());

        expect($post->fresh()->status)->toBe('processing');
        Storage::disk('s3')->assertExists($post->media_path);
    });

    it('erro do media_publish grava mensagem mapeada: sem JSON, sem body da Meta e sem token no post, na notificação e no log', function () {
        $logged = [];
        Log::shouldReceive('info', 'warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnUsing(function (string $message, array $context = []) use (&$logged) {
            $logged[] = $message.json_encode($context);
        });
        [$account, $post] = igJobPost();
        Http::fake([
            '*/cj-1*' => Http::response(['status_code' => 'FINISHED']),
            '*/media_publish' => Http::response(['error' => ['code' => 100, 'message' => 'Texto cru SEGREDO-IG', 'fbtrace_id' => 'TRACE-XYZ']], 400),
        ]);

        igRunJob($post, $account);

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->toBe(InstagramError::UNKNOWN->message());
        expect(json_encode(Notification::all()->toArray()))->not->toContain('SEGREDO-IG')->not->toContain('TRACE-XYZ');
        expect($logged)->not->toBeEmpty()
            ->and(implode('', $logged))->not->toContain('SEGREDO-IG')->not->toContain('TRACE-XYZ')->not->toContain('Texto cru');
    });

});
