<?php

use App\Enums\ScheduledPostStatus;
use App\Events\ScheduledPostEvent;
use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// QA da spec 021: lacunas de cobertura além de AwaitingUploadTest (corridas, thumbnail, fuso, retry, limites, regressões).

const AQ_EXPIRED_TEXT = 'O envio do vídeo não foi concluído. Envie o vídeo novamente.';

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
    Carbon::setTestNow(Carbon::now()->startOfSecond());
    $this->t0 = now();
    Queue::fake([PublishPostJob::class]);
    Storage::fake('s3');
    $this->user = createUserWithPermissions(['posts.create', 'posts.view', 'posts.delete']);
});

afterEach(fn () => Carbon::setTestNow());

function aqPath(User $user, string $name = 'a.mp4', string $dir = 'videos', bool $store = true): string
{
    $path = 'workspaces/'.$user->currentAccess->workspace->uuid."/{$dir}/{$name}";
    $store && Storage::disk('s3')->put($path, 'conteudo');

    return $path;
}

function aqPost(User $user, string $path, array $overrides = []): ScheduledPost
{
    return ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $user->id,
        'social_account_id' => connectedSocialAccount($user, 'youtube')->id,
        'platform' => 'youtube',
        'title' => 'Reservado',
        'media_path' => $path,
        'payload' => [],
        'scheduled_at' => now()->addMinutes(5),
        'status' => 'awaiting_upload',
    ], $overrides));
}

function aqComplete(User $user, string $path): TestResponse
{
    return test()->withToken($user->test_token)->postJson('/api/scheduled-posts/upload-complete', ['media_storage_path' => $path]);
}

function aqStatus(ScheduledPost $post): string
{
    return ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->value('status');
}

function aqAge(ScheduledPost $post, Carbon $createdAt): void
{
    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
}

describe('corridas em upload-complete (ponto 1)', function () {

    it('cancel que acontece entre a conferência do S3 e a trava: o post segue cancelled e nenhum job sai', function () {
        $path = aqPath($this->user);
        $post = aqPost($this->user, $path);

        $this->partialMock(StorageService::class, function ($mock) use ($post) {
            $mock->shouldReceive('exists')->andReturnUsing(function () use ($post) {
                ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['status' => 'cancelled']);

                return true;
            });
        });

        aqComplete($this->user, $path)->assertOk();

        expect(aqStatus($post))->toBe('cancelled')
            ->and($post->fresh()->upload_completed_at)->toBeNull();
        Queue::assertNothingPushed();
    });

    it('outra confirmação que libera o post no meio: esta chamada não despacha job duplicado', function () {
        $path = aqPath($this->user);
        $post = aqPost($this->user, $path);

        $this->partialMock(StorageService::class, function ($mock) use ($post) {
            $mock->shouldReceive('exists')->andReturnUsing(function () use ($post) {
                // Simula a "outra requisição" que já liberou o post (e despachou o próprio job).
                ScheduledPost::withoutGlobalScopes()->whereKey($post->id)->update(['status' => 'pending', 'upload_completed_at' => now()]);

                return true;
            });
        });

        aqComplete($this->user, $path)->assertOk()->assertJsonPath('late', false);

        Queue::assertNothingPushed();
        expect(Notification::where('type', 'post_upload_late')->count())->toBe(0);
    });

    it('cancel e upload-complete em sequência: cancelar depois de liberado também funciona e a mídia some (nenhum outro post usa)', function () {
        $path = aqPath($this->user);
        $post = aqPost($this->user, $path);

        aqComplete($this->user, $path)->assertOk();
        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertOk();

        expect(aqStatus($post))->toBe('cancelled');
        Storage::disk('s3')->assertMissing($path);
    });
});

describe('thumbnail (ponto 2)', function () {

    it('post com thumbnail ausente no S3: 422 upload_not_found e o post segue awaiting_upload', function () {
        $path = aqPath($this->user);
        $thumb = aqPath($this->user, 't.jpg', 'images', store: false);
        $post = aqPost($this->user, $path, ['payload' => ['thumbnail_path' => $thumb]]);

        aqComplete($this->user, $path)
            ->assertStatus(422)
            ->assertJsonPath('error', 'upload_not_found');

        expect(aqStatus($post))->toBe('awaiting_upload');
        Queue::assertNothingPushed();
    });

    it('post com vídeo e thumbnail presentes é liberado', function () {
        $path = aqPath($this->user);
        $thumb = aqPath($this->user, 't.jpg', 'images');
        $post = aqPost($this->user, $path, ['payload' => ['thumbnail_path' => $thumb]]);

        aqComplete($this->user, $path)->assertOk();

        expect(aqStatus($post))->toBe('pending');
        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('vídeo ausente com thumbnail presente: 422', function () {
        $path = aqPath($this->user, store: false);
        $thumb = aqPath($this->user, 't.jpg', 'images');
        $post = aqPost($this->user, $path, ['payload' => ['thumbnail_path' => $thumb]]);

        aqComplete($this->user, $path)->assertStatus(422)->assertJsonPath('error', 'upload_not_found');

        expect(aqStatus($post))->toBe('awaiting_upload');
    });
});

describe('vários posts do mesmo path (ponto 3)', function () {

    it('com um cancelado e outro aguardando: libera só o aguardando e não revive o cancelado', function () {
        $path = aqPath($this->user);
        $cancelled = aqPost($this->user, $path, ['status' => 'cancelled']);
        $awaiting = aqPost($this->user, $path);

        aqComplete($this->user, $path)->assertOk();

        expect(aqStatus($cancelled))->toBe('cancelled')
            ->and(aqStatus($awaiting))->toBe('pending');
        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('com um failed e outro aguardando: o failed não é tocado e não gera job', function () {
        $path = aqPath($this->user);
        $failed = aqPost($this->user, $path, ['status' => 'failed', 'error_message' => 'x']);
        $awaiting = aqPost($this->user, $path);

        aqComplete($this->user, $path)->assertOk();

        expect(aqStatus($failed))->toBe('failed')
            ->and(aqStatus($awaiting))->toBe('pending');
        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('todos cancelados: 409 sem job e sem alterar nada (não vaza 404/500)', function () {
        $path = aqPath($this->user);
        $a = aqPost($this->user, $path, ['status' => 'cancelled']);
        $b = aqPost($this->user, $path, ['status' => 'cancelled']);

        aqComplete($this->user, $path)->assertStatus(409)->assertJsonPath('message', 'O agendamento foi cancelado.');

        expect(aqStatus($a))->toBe('cancelled')->and(aqStatus($b))->toBe('cancelled');
        Queue::assertNothingPushed();
    });

    it('cancelar um de dois awaiting_upload do mesmo path mantém a mídia; cancelar o último apaga', function () {
        $path = aqPath($this->user);
        $a = aqPost($this->user, $path);
        $b = aqPost($this->user, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$a->uuid}")->assertOk();
        Storage::disk('s3')->assertExists($path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$b->uuid}")->assertOk();
        Storage::disk('s3')->assertMissing($path);
    });

    it('posts de paths diferentes não se misturam', function () {
        $p1 = aqPath($this->user, 'um.mp4');
        $p2 = aqPath($this->user, 'dois.mp4');
        $a = aqPost($this->user, $p1);
        $b = aqPost($this->user, $p2);

        aqComplete($this->user, $p1)->assertOk();

        expect(aqStatus($a))->toBe('pending')->and(aqStatus($b))->toBe('awaiting_upload');
    });
});

describe('fuso horário e late/message (ponto 4)', function () {

    it('scheduled_at enviado em UTC (Z) é gravado como o mesmo instante e a mensagem mostra HH:MM no fuso da aplicação', function () {
        $account = connectedSocialAccount($this->user, 'youtube');
        $path = aqPath($this->user, store: false);
        $scheduledUtc = $this->t0->copy()->addMinutes(6)->utc();

        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', [
            'social_account_uuids' => [$account->uuid],
            'title' => 'T',
            'caption' => 'C',
            'media_storage_path' => $path,
            'media_pending' => true,
            'scheduled_at' => $scheduledUtc->format('Y-m-d\TH:i:s\Z'),
        ])->assertCreated();

        $post = ScheduledPost::withoutGlobalScopes()->sole();
        expect($post->scheduled_at->timestamp)->toBe($scheduledUtc->timestamp);

        Storage::disk('s3')->put($path, 'x');
        Carbon::setTestNow($this->t0->copy()->addMinutes(9));

        $local = config('app.timezone');
        $expected = 'O envio do vídeo terminou às '.now()->setTimezone($local)->format('H:i')
            .', depois do horário agendado ('.$scheduledUtc->copy()->setTimezone($local)->format('H:i').'). Estamos publicando agora.';

        aqComplete($this->user, $path)->assertOk()->assertJsonPath('late', true)->assertJsonPath('message', $expected);
        // Não pode exibir a hora em UTC (3h de diferença do fuso da aplicação).
        expect($expected)->not->toContain($scheduledUtc->format('H:i').')');
    });

    it('envio terminando exatamente no horário agendado não é atrasado (apenas depois conta como late)', function () {
        $path = aqPath($this->user);
        aqPost($this->user, $path, ['scheduled_at' => $this->t0->copy()->addMinutes(5)]);
        Carbon::setTestNow($this->t0->copy()->addMinutes(5));

        aqComplete($this->user, $path)->assertOk()->assertJsonPath('late', false);
        expect(Notification::where('type', 'post_upload_late')->count())->toBe(0);
    });

    it('um segundo depois do horário já é late', function () {
        $path = aqPath($this->user);
        aqPost($this->user, $path, ['scheduled_at' => $this->t0->copy()->addMinutes(5)]);
        Carbon::setTestNow($this->t0->copy()->addMinutes(5)->addSecond());

        aqComplete($this->user, $path)->assertOk()->assertJsonPath('late', true);
    });

    it('vários posts do path atrasados geram uma notificação por post/autor', function () {
        $path = aqPath($this->user);
        aqPost($this->user, $path);
        aqPost($this->user, $path);
        Carbon::setTestNow($this->t0->copy()->addMinutes(10));

        aqComplete($this->user, $path)->assertOk()->assertJsonPath('late', true);

        expect(Notification::where('type', 'post_upload_late')->count())->toBe(2);
    });
});

describe('retry de post failed por expiração (ponto 5)', function () {

    function aqExpire(): void
    {
        test()->artisan('posts:expire-awaiting-uploads')->assertSuccessful();
    }

    it('post expirado com vídeo nunca enviado: retry devolve 422 media_expired e não despacha job', function () {
        $path = aqPath($this->user, store: false);
        $post = aqPost($this->user, $path);
        aqAge($post, now()->subHours(25));
        aqExpire();
        expect(aqStatus($post))->toBe('failed');

        $this->withToken($this->user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(422)
            ->assertJsonPath('error', 'media_expired');

        expect(aqStatus($post))->toBe('failed');
        Queue::assertNothingPushed();
    });

    it('post expirado cujo vídeo chegou ao S3 depois: retry reenvia', function () {
        $path = aqPath($this->user, store: false);
        $post = aqPost($this->user, $path);
        aqAge($post, now()->subHours(25));
        aqExpire();
        Storage::disk('s3')->put($path, 'conteudo');

        $this->withToken($this->user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(PublishPostJob::class, 1);
        expect($post->fresh()->error_message)->toBeNull();
    });

    it('post expirado com thumbnail ausente: retry devolve 422 media_expired', function () {
        $path = aqPath($this->user);
        $thumb = aqPath($this->user, 't.jpg', 'images', store: false);
        $post = aqPost($this->user, $path, ['payload' => ['thumbnail_path' => $thumb]]);
        aqAge($post, now()->subHours(25));
        aqExpire();

        $this->withToken($this->user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry")
            ->assertStatus(422)
            ->assertJsonPath('error', 'media_expired');
        Queue::assertNothingPushed();
    });

    it('post expirado: o resource marca can_retry e o prazo, pois a mídia ainda não foi limpa', function () {
        $post = aqPost($this->user, aqPath($this->user));
        aqAge($post, now()->subHours(25));
        aqExpire();

        expect($post->fresh()->canRetry())->toBeTrue();
    });
});

describe('comando de expiração (ponto 6)', function () {

    it('limite exato de 24h não expira; 24h e 1s expira', function () {
        $exact = aqPost($this->user, aqPath($this->user, 'e.mp4'));
        $over = aqPost($this->user, aqPath($this->user, 'o.mp4'));
        aqAge($exact, now()->subHours(24));
        aqAge($over, now()->subHours(24)->subSecond());

        aqExpire();

        expect(aqStatus($exact))->toBe('awaiting_upload')
            ->and(aqStatus($over))->toBe('failed');
    });

    it('é idempotente: rodar duas vezes gera uma única notificação e um único evento', function () {
        $post = aqPost($this->user, aqPath($this->user));
        aqAge($post, now()->subHours(30));
        Event::fake([ScheduledPostEvent::class]);

        aqExpire();
        $first = $post->fresh();
        aqExpire();

        expect(aqStatus($post))->toBe('failed')
            ->and($post->fresh()->error_message)->toBe(AQ_EXPIRED_TEXT)
            ->and($post->fresh()->failed_at->timestamp)->toBe($first->failed_at->timestamp)
            ->and(Notification::where('type', 'post_failed')->count())->toBe(1);
        Event::assertDispatchedTimes(ScheduledPostEvent::class, 1);
    });

    it('não toca em posts de outros status, mesmo muito antigos', function () {
        $others = collect(['pending', 'processing', 'published', 'failed', 'cancelled'])->map(function ($status) {
            $post = aqPost($this->user, aqPath($this->user, "{$status}.mp4"), ['status' => $status, 'error_message' => $status === 'failed' ? 'erro original' : null]);
            aqAge($post, now()->subDays(10));

            return $post;
        });

        aqExpire();

        foreach ($others as $post) {
            expect(aqStatus($post))->toBe($post->status);
        }
        expect($others->last()->fresh()->error_message)->toBeNull()
            ->and($others[3]->fresh()->error_message)->toBe('erro original');
    });

    it('expira posts de vários workspaces (rotina global)', function () {
        $other = createUserWithPermissions(['posts.create']);
        $a = aqPost($this->user, aqPath($this->user));
        $b = aqPost($other, aqPath($other));
        aqAge($a, now()->subHours(30));
        aqAge($b, now()->subHours(30));

        aqExpire();

        expect(aqStatus($a))->toBe('failed')->and(aqStatus($b))->toBe('failed');
    });

    it('post expirado não é despachado nem liberado por upload-complete posterior', function () {
        $path = aqPath($this->user);
        $post = aqPost($this->user, $path);
        aqAge($post, now()->subHours(30));
        aqExpire();

        aqComplete($this->user, $path)->assertStatus(409)->assertJsonPath('message', 'O prazo do envio expirou. Envie o vídeo novamente.');

        expect(aqStatus($post))->toBe('failed');
        Queue::assertNothingPushed();
    });

    it('post cuja confirmação chegou antes do comando (já pending) não é expirado', function () {
        $path = aqPath($this->user);
        $post = aqPost($this->user, $path);
        aqAge($post, now()->subHours(30));
        aqComplete($this->user, $path)->assertOk();

        aqExpire();

        expect(aqStatus($post))->toBe('pending');
    });
});

describe('regressão de deleteIfUnused (ponto 7)', function () {

    it('cancelar um post pending que compartilha o path com um awaiting_upload mantém o arquivo', function () {
        $path = aqPath($this->user);
        $pending = aqPost($this->user, $path, ['status' => 'pending']);
        aqPost($this->user, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$pending->uuid}")->assertOk();

        Storage::disk('s3')->assertExists($path);
    });

    it('cancelar pending sem outro post usando o path continua apagando a mídia', function () {
        $path = aqPath($this->user);
        $thumb = aqPath($this->user, 't.jpg', 'images');
        $pending = aqPost($this->user, $path, ['status' => 'pending', 'payload' => ['thumbnail_path' => $thumb]]);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$pending->uuid}")->assertOk();

        Storage::disk('s3')->assertMissing($path);
        Storage::disk('s3')->assertMissing($thumb);
    });

    it('cancelar awaiting_upload com o outro post já cancelado apaga a mídia', function () {
        $path = aqPath($this->user);
        aqPost($this->user, $path, ['status' => 'cancelled']);
        $awaiting = aqPost($this->user, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$awaiting->uuid}")->assertOk();

        Storage::disk('s3')->assertMissing($path);
    });

    it('limpeza de mídia de failed antigo não apaga arquivo usado por awaiting_upload', function () {
        $path = aqPath($this->user);
        $failed = aqPost($this->user, $path, ['status' => 'failed', 'error_message' => 'x']);
        ScheduledPost::withoutGlobalScopes()->whereKey($failed->id)->update(['failed_at' => now()->subDays(6)]);
        aqPost($this->user, $path);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertExists($path);
    });

    it('limpeza de mídia de failed antigo continua apagando quando ninguém mais usa o arquivo', function () {
        $path = aqPath($this->user);
        $failed = aqPost($this->user, $path, ['status' => 'failed', 'error_message' => 'x']);
        ScheduledPost::withoutGlobalScopes()->whereKey($failed->id)->update(['failed_at' => now()->subDays(6)]);

        $this->artisan('posts:purge-failed-media')->assertSuccessful();

        Storage::disk('s3')->assertMissing($path);
    });

    it('retry de failed comum não é afetado e não apaga mídia', function () {
        $path = aqPath($this->user);
        $failed = aqPost($this->user, $path, ['status' => 'failed', 'error_message' => 'x']);
        ScheduledPost::withoutGlobalScopes()->whereKey($failed->id)->update(['failed_at' => now()->subHour()]);

        $this->withToken($this->user->test_token)->postJson("/api/scheduled-posts/{$failed->uuid}/retry")->assertOk();

        Storage::disk('s3')->assertExists($path);
        Queue::assertPushed(PublishPostJob::class, 1);
    });
});

describe('coluna status como string (ponto 8)', function () {

    it('status é varchar sem CHECK: aceita todos os valores do enum e o default é pending', function () {
        $columns = collect(Schema::getColumns('scheduled_posts'))->keyBy('name');

        expect($columns['status']['type_name'])->toBeIn(['varchar', 'character varying', 'string'])
            ->and($columns->has('upload_completed_at'))->toBeTrue();

        foreach (ScheduledPostStatus::cases() as $i => $status) {
            $post = aqPost($this->user, aqPath($this->user, "s{$i}.mp4"), ['status' => $status->value]);
            expect(aqStatus($post))->toBe($status->value);
        }
    });

    it('post criado sem status explícito fica pending (default preservado pela migration)', function () {
        $post = ScheduledPost::withoutGlobalScopes()->create([
            'user_id' => $this->user->id,
            'social_account_id' => connectedSocialAccount($this->user, 'youtube')->id,
            'platform' => 'youtube',
            'title' => 'x',
            'media_path' => aqPath($this->user),
            'payload' => [],
        ]);

        expect(aqStatus($post->fresh()))->toBe('pending');
    });
});

describe('fluxo sem media_pending inalterado (ponto 9)', function () {

    function aqStore(User $user, string $path, array $body = []): TestResponse
    {
        return test()->withToken($user->test_token)->postJson('/api/scheduled-posts', array_merge([
            'social_account_uuids' => [connectedSocialAccount($user, 'youtube')->uuid],
            'title' => 'T',
            'caption' => 'C',
            'media_storage_path' => $path,
            'scheduled_at' => now()->addMinutes(6)->toIso8601String(),
        ], $body));
    }

    it('sem a flag, o arquivo precisa existir no S3: 422 e nada criado', function () {
        aqStore($this->user, aqPath($this->user, store: false))->assertStatus(422)->assertJsonValidationErrors('media_storage_path');

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('media_pending=false explícito também exige o arquivo e cria pending com job', function () {
        aqStore($this->user, aqPath($this->user, 'f.mp4', store: false), ['media_pending' => false])->assertStatus(422);
        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);

        aqStore($this->user, aqPath($this->user, 'g.mp4'), ['media_pending' => false])
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'pending');

        Queue::assertPushed(PublishPostJob::class, 1);
        expect(ScheduledPost::withoutGlobalScopes()->sole()->upload_completed_at)->toBeNull();
    });

    it('sem a flag, antecedência insuficiente continua 422', function () {
        aqStore($this->user, aqPath($this->user), ['scheduled_at' => now()->addMinutes(4)->toIso8601String()])->assertStatus(422);
        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('media_pending dispensa só a existência: path de outro diretório continua inválido', function () {
        aqStore($this->user, 'workspaces/'.$this->user->currentAccess->workspace->uuid.'/outro/a.mp4', ['media_pending' => true])
            ->assertStatus(422);
        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('thumbnail com media_pending é aceita sem existir no S3, mas continua exigindo o prefixo do workspace', function () {
        $path = aqPath($this->user, store: false);
        aqStore($this->user, $path, ['media_pending' => true, 'thumbnail_storage_path' => 'workspaces/outro-ws/images/t.jpg'])
            ->assertStatus(422);
        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);

        aqStore($this->user, $path, ['media_pending' => true, 'thumbnail_storage_path' => aqPath($this->user, 't.jpg', 'thumbnails', store: false)])
            ->assertCreated();
    });
});

describe('mensagens de erro (ponto 10)', function () {

    it('422 de arquivo ausente: mensagem fixa em pt-BR, sem bucket, chave ou detalhe de S3', function () {
        $path = aqPath($this->user, store: false);
        aqPost($this->user, $path);

        $response = aqComplete($this->user, $path)->assertStatus(422);
        $body = $response->getContent();

        $response->assertJsonPath('error', 'upload_not_found')
            ->assertJsonPath('message', 'O vídeo ainda não foi encontrado no armazenamento. Aguarde o envio terminar e tente novamente.');
        expect($body)->not->toContain($path)->not->toContain('s3')->not->toContain('Bucket')->not->toContain('Aws');
    });

    it('falha do S3 na conferência não vaza a mensagem da exceção', function () {
        $path = aqPath($this->user);
        $post = aqPost($this->user, $path);
        config(['app.debug' => false]); // produção: o handler não pode expor a mensagem da exceção
        $this->partialMock(StorageService::class, function ($mock) {
            $mock->shouldReceive('exists')->andThrow(new RuntimeException('SEGREDO-bucket-xyz AccessDenied'));
        });

        $response = aqComplete($this->user, $path);

        expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
            ->and($response->getContent())->not->toContain('SEGREDO-bucket-xyz');
        expect(aqStatus($post))->toBe('awaiting_upload');
        Queue::assertNothingPushed();
    });

    it('path de outro workspace e path vazio dão 422 em pt-BR sem tocar nada', function () {
        $post = aqPost($this->user, aqPath($this->user));

        aqComplete($this->user, 'workspaces/00000000-0000-0000-0000-000000000000/videos/a.mp4')
            ->assertStatus(422)
            ->assertJsonValidationErrors('media_storage_path');
        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts/upload-complete', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.media_storage_path.0', 'Informe o caminho do vídeo enviado.');

        expect(aqStatus($post))->toBe('awaiting_upload');
    });

    it('post expirado guarda só o texto fixo (nada de detalhe técnico) e a listagem o expõe', function () {
        $post = aqPost($this->user, aqPath($this->user));
        aqAge($post, now()->subHours(30));
        aqExpire();

        $this->withToken($this->user->test_token)->getJson('/api/scheduled-posts?status=failed')
            ->assertOk()
            ->assertJsonPath('data.0.error_message', AQ_EXPIRED_TEXT);
    });
});

describe('permissões e isolamento extras', function () {

    it('usuário com posts.view mas sem posts.create recebe 403 em upload-complete e nada muda', function () {
        $viewer = createUserWithPermissions(['posts.view']);
        $path = aqPath($viewer);
        $post = aqPost($viewer, $path);

        aqComplete($viewer, $path)->assertForbidden();

        expect(aqStatus($post))->toBe('awaiting_upload');
        Queue::assertNothingPushed();
    });

    it('expiração e cancelamento de um workspace não afetam posts de outro', function () {
        $other = createUserWithPermissions(['posts.create', 'posts.delete']);
        $mine = aqPost($this->user, aqPath($this->user));
        $theirs = aqPost($other, aqPath($other));

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$theirs->uuid}")->assertNotFound();

        expect(aqStatus($theirs))->toBe('awaiting_upload')->and(aqStatus($mine))->toBe('awaiting_upload');
    });
});
