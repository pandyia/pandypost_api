<?php

use App\Console\Commands\ExpireAwaitingUploadsCommand;
use App\Enums\Exceptions\ScheduledPostError;
use App\Enums\ScheduledPostStatus;
use App\Events\ScheduledPostEvent;
use App\Jobs\PublishPostJob;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// Spec 021: reserva do agendamento no clique (awaiting_upload) e confirmação do envio (upload-complete).

const AW_EXPIRED_TEXT = 'O envio do vídeo não foi concluído. Envie o vídeo novamente.';

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
    // Congela no instante real (sem segundos fracionados): o `after:+N minutes` do validador usa o relógio real.
    Carbon::setTestNow(Carbon::now()->startOfSecond());
    $this->t0 = now();
    // Só o job de publicação é falsificado: as notificações seguem a fila real (sync nos testes) e gravam a linha no banco.
    Queue::fake([PublishPostJob::class]);
    Storage::fake('s3');
    $this->user = createUserWithPermissions(['posts.create', 'posts.view', 'posts.delete']);
    $this->ws = $this->user->currentAccess->workspace->uuid;
});

afterEach(fn () => Carbon::setTestNow());

/** Grava o arquivo no S3 fake do workspace do usuário e devolve o path. */
function awFile(User $user, string $name = 'a.mp4', bool $store = true): string
{
    $path = 'workspaces/'.$user->currentAccess->workspace->uuid."/videos/{$name}";
    $store && Storage::disk('s3')->put($path, 'conteudo');

    return $path;
}

function awReserve(User $user, string $path, array $body = []): TestResponse
{
    $account = connectedSocialAccount($user, 'youtube');

    return test()->withToken($user->test_token)->postJson('/api/scheduled-posts', array_merge([
        'social_account_uuids' => [$account->uuid],
        'title' => 'Título',
        'caption' => 'Legenda',
        'media_storage_path' => $path,
        'media_pending' => true,
        'scheduled_at' => now()->addMinutes(5)->addSeconds(30)->toIso8601String(),
    ], $body));
}

function awPost(User $user, string $path, array $overrides = []): ScheduledPost
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

function awComplete(User $user, ?string $path): TestResponse
{
    return test()->withToken($user->test_token)->postJson('/api/scheduled-posts/upload-complete', ['media_storage_path' => $path]);
}

function awOldCreated(ScheduledPost $post, int $hoursAgo): void
{
    // Query direta: o Eloquent sobrescreveria os timestamps.
    ScheduledPost::withoutGlobalScopes()->whereKey($post->id)
        ->update(['created_at' => now()->subHours($hoursAgo), 'updated_at' => now()->subHours($hoursAgo)]);
}

function awSnapshot(): array
{
    return ScheduledPost::withoutGlobalScopes()->orderBy('id')->get(['id', 'status', 'upload_completed_at', 'error_message'])->toArray();
}

describe('configuração e enum', function () {

    it('antecedência mínima padrão é 5 minutos e pode ser sobrescrita', function () {
        expect(config('scheduling.min_lead_minutes'))->toBe(5);

        config(['scheduling.min_lead_minutes' => 15]);
        expect(config('scheduling.min_lead_minutes'))->toBe(15);
    });

    it('expira o envio em 24h por padrão', function () {
        expect(config('scheduling.upload_expiration_hours'))->toBe(24);
    });

    it('o status awaiting_upload existe com rótulo em pt-BR', function () {
        expect(ScheduledPostStatus::tryFrom('awaiting_upload'))->not->toBeNull()
            ->and(ScheduledPostStatus::tryFrom('awaiting_upload')->label())->toBe('Aguardando envio do vídeo');
    });

    it('erros de domínio do envio têm mensagem fixa em pt-BR', function () {
        expect(ScheduledPostError::tryFrom('upload_expired')?->message())->toBe(AW_EXPIRED_TEXT)
            ->and(ScheduledPostError::tryFrom('upload_not_found')?->message())->toBeString()->not->toBeEmpty();
    });
});

describe('POST /scheduled-posts com media_pending', function () {

    it('clique com +5 min: 201, post awaiting_upload e nenhum job despachado', function () {
        $path = awFile($this->user, store: false); // o vídeo ainda está subindo

        awReserve($this->user, $path)
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'awaiting_upload');

        $post = ScheduledPost::withoutGlobalScopes()->sole();
        expect($post->status)->toBe('awaiting_upload')
            ->and($post->media_path)->toBe($path)
            ->and($post->upload_completed_at)->toBeNull();
        Queue::assertNothingPushed();
    });

    it('cria um post awaiting_upload por conta, todos com o mesmo path', function () {
        $path = awFile($this->user);
        $a = connectedSocialAccount($this->user, 'youtube');
        $b = connectedSocialAccount($this->user, 'youtube');

        awReserve($this->user, $path, ['social_account_uuids' => [$a->uuid, $b->uuid]])->assertCreated();

        expect(ScheduledPost::withoutGlobalScopes()->where('status', 'awaiting_upload')->where('media_path', $path)->count())->toBe(2);
        Queue::assertNothingPushed();
    });

    it('clique com +4 min (antecedência 5): 422 e nada é criado', function () {
        awReserve($this->user, awFile($this->user), ['scheduled_at' => now()->addMinutes(4)->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at']);

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('sem a flag o comportamento atual não muda: pending e job despachado com delay', function () {
        $path = awFile($this->user);
        $at = now()->addHour();

        awReserve($this->user, $path, ['media_pending' => false, 'scheduled_at' => $at->toIso8601String()])
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'pending');

        expect(ScheduledPost::withoutGlobalScopes()->sole()->status)->toBe('pending');
        Queue::assertPushed(PublishPostJob::class, 1);
    });

    it('retorna 403 sem posts.create', function () {
        $user = createUserWithPermissions(['posts.view']);

        awReserve($user, awFile($user))->assertForbidden();

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('retorna 401 sem token', function () {
        $this->postJson('/api/scheduled-posts', ['media_pending' => true])->assertUnauthorized();
    });

    it('recusa media_pending não booleano', function () {
        awReserve($this->user, awFile($this->user), ['media_pending' => 'talvez'])->assertUnprocessable();
        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('recusa path de outro workspace com media_pending (422, nada criado)', function () {
        $other = createUserWithPermissions(['posts.create']);

        awReserve($this->user, awFile($other))->assertUnprocessable();

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('antecedência vinda da config (Store e Retry)', function () {

    it('Store: com 15 minutos de antecedência recusa 10 minutos e a mensagem mostra 15', function () {
        config(['scheduling.min_lead_minutes' => 15]);

        awReserve($this->user, awFile($this->user), ['media_pending' => false, 'scheduled_at' => now()->addMinutes(10)->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonPath('errors.scheduled_at.0', 'O agendamento precisa ser de pelo menos 15 minutos no futuro.');

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('Store: com 15 minutos aceita 16 minutos', function () {
        config(['scheduling.min_lead_minutes' => 15]);

        awReserve($this->user, awFile($this->user), ['media_pending' => false, 'scheduled_at' => now()->addMinutes(16)->toIso8601String()])
            ->assertCreated();
    });

    it('Retry: com 15 minutos de antecedência recusa 10 minutos e a mensagem mostra 15', function () {
        config(['scheduling.min_lead_minutes' => 15]);
        $path = awFile($this->user);
        $post = awPost($this->user, $path, ['status' => 'failed', 'failed_at' => now()->subHour(), 'error_message' => 'x']);

        $this->withToken($this->user->test_token)
            ->postJson("/api/scheduled-posts/{$post->uuid}/retry", ['scheduled_at' => now()->addMinutes(10)->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonPath('errors.scheduled_at.0', 'O agendamento precisa ser de pelo menos 15 minutos no futuro.');

        expect($post->fresh()->status)->toBe('failed');
        Queue::assertNothingPushed();
    });
});

describe('POST /scheduled-posts/upload-complete', function () {

    it('upload-complete 2 min após o clique, post para +5 min: vira pending, grava upload_completed_at e despacha job com delay até 16:50; late false', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path, ['scheduled_at' => $this->t0->copy()->addMinutes(5)]);
        Carbon::setTestNow($this->t0->copy()->addMinutes(2));

        awComplete($this->user, $path)
            ->assertOk()
            ->assertJsonPath('late', false)
            ->assertJsonPath('data.0.uuid', $post->uuid)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonStructure(['message', 'late', 'data']);

        $post->refresh();
        expect($post->status)->toBe('pending')
            ->and($post->upload_completed_at->timestamp)->toBe(now()->timestamp);
        Queue::assertPushed(PublishPostJob::class, 1);
        Queue::assertPushed(PublishPostJob::class, fn ($job) => Carbon::instance($job->delay)->timestamp === $post->scheduled_at->timestamp);
        expect(Notification::where('type', 'post_upload_late')->count())->toBe(0);
    });

    it('upload-complete 8 min após o clique, post para +5 min: job imediato, late true, mensagem com os horários e notificação ao autor', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path, ['scheduled_at' => $this->t0->copy()->addMinutes(5)]);
        Carbon::setTestNow($this->t0->copy()->addMinutes(8));

        awComplete($this->user, $path)
            ->assertOk()
            ->assertJsonPath('late', true)
            ->assertJsonPath('message', 'O envio do vídeo terminou às '.$this->t0->copy()->addMinutes(8)->format('H:i').', depois do horário agendado ('.$this->t0->copy()->addMinutes(5)->format('H:i').'). Estamos publicando agora.');

        expect($post->fresh()->status)->toBe('pending');
        Queue::assertPushed(PublishPostJob::class, 1);
        Queue::assertPushed(PublishPostJob::class, fn ($job) => $job->delay === null || Carbon::instance($job->delay)->lte(now()));

        $notifications = Notification::where('type', 'post_upload_late')->get();
        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($this->user->id);
    });

    it('a notificação post_upload_late vai só ao autor, não a outro membro do workspace', function () {
        $member = addUserToWorkspace($this->user->currentAccess->workspace);
        $path = awFile($this->user);
        awPost($this->user, $path, ['scheduled_at' => now()->addMinutes(5)]);
        Carbon::setTestNow($this->t0->copy()->addMinutes(30));

        awComplete($this->user, $path)->assertOk()->assertJsonPath('late', true);

        expect(Notification::where('type', 'post_upload_late')->where('notifiable_id', $member->id)->count())->toBe(0);
    });

    it('post sem scheduled_at (publicação imediata) vira pending com job imediato e late false', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path, ['scheduled_at' => null]);

        awComplete($this->user, $path)->assertOk()->assertJsonPath('late', false);

        expect($post->fresh()->status)->toBe('pending');
        Queue::assertPushed(PublishPostJob::class, 1);
        expect(Notification::where('type', 'post_upload_late')->count())->toBe(0);
    });

    it('libera todos os posts awaiting_upload do mesmo path e não toca em outros paths', function () {
        $path = awFile($this->user, 'a.mp4');
        $otherPath = awFile($this->user, 'b.mp4');
        $p1 = awPost($this->user, $path);
        $p2 = awPost($this->user, $path);
        $untouched = awPost($this->user, $otherPath);

        awComplete($this->user, $path)->assertOk()->assertJsonCount(2, 'data');

        expect($p1->fresh()->status)->toBe('pending')
            ->and($p2->fresh()->status)->toBe('pending')
            ->and($untouched->fresh()->status)->toBe('awaiting_upload')
            ->and($untouched->fresh()->upload_completed_at)->toBeNull();
        Queue::assertPushed(PublishPostJob::class, 2);
    });

    it('é idempotente: a segunda chamada não despacha job duplicado nem altera o post', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path);

        awComplete($this->user, $path)->assertOk();
        $completedAt = $post->fresh()->upload_completed_at;
        Carbon::setTestNow(now()->addMinutes(2));

        awComplete($this->user, $path)->assertStatus(200);

        Queue::assertPushed(PublishPostJob::class, 1);
        expect($post->fresh()->upload_completed_at->timestamp)->toBe($completedAt->timestamp)
            ->and($post->fresh()->status)->toBe('pending');
    });

    it('não altera posts que já saíram de awaiting_upload (published/cancelled/failed no mesmo path)', function () {
        $path = awFile($this->user);
        $cancelled = awPost($this->user, $path, ['status' => 'cancelled']);
        $failed = awPost($this->user, $path, ['status' => 'failed', 'error_message' => AW_EXPIRED_TEXT]);

        // Só failed (expirado) e cancelled, sem nenhum confirmado: 409 (RF-3b); nunca 405/500.
        awComplete($this->user, $path)->assertStatus(409)->assertJsonPath('message', 'O prazo do envio expirou. Envie o vídeo novamente.');

        expect($cancelled->fresh()->status)->toBe('cancelled')
            ->and($failed->fresh()->status)->toBe('failed')
            ->and($failed->fresh()->upload_completed_at)->toBeNull();
        Queue::assertNothingPushed();
    });

    it('arquivo ausente no S3: 422 com erro de domínio e o post continua awaiting_upload', function () {
        $path = awFile($this->user, store: false);
        $post = awPost($this->user, $path);

        awComplete($this->user, $path)
            ->assertUnprocessable()
            ->assertJsonPath('error', ScheduledPostError::tryFrom('upload_not_found')?->value);

        expect($post->fresh()->status)->toBe('awaiting_upload')
            ->and($post->fresh()->upload_completed_at)->toBeNull();
        Queue::assertNothingPushed();
        expect(Notification::where('type', 'post_upload_late')->count())->toBe(0);
    });

    it('valida o body: path ausente ou fora do workspace dá 422 e nada muda', function (?string $path) {
        $post = awPost($this->user, awFile($this->user));
        $before = awSnapshot();

        awComplete($this->user, $path)->assertUnprocessable();

        expect(awSnapshot())->toBe($before);
        Queue::assertNothingPushed();
    })->with([
        'ausente' => [null],
        'traversal' => ['workspaces/x/../../etc/passwd'],
        'diretório inválido' => ['workspaces/abc/outro/a.mp4'],
    ]);

    it('retorna 404 quando não há posts para o path no workspace', function () {
        $path = awFile($this->user, 'sem-post.mp4');

        awComplete($this->user, $path)->assertNotFound();

        Queue::assertNothingPushed();
    });

    it('retorna 403 sem posts.create e nada muda', function () {
        $viewer = createUserWithPermissions(['posts.view']);
        $path = awFile($viewer);
        awPost($viewer, $path);
        $before = awSnapshot();

        awComplete($viewer, $path)->assertForbidden();

        expect(awSnapshot())->toBe($before);
        Queue::assertNothingPushed();
    });

    it('retorna 401 sem token', function () {
        $this->postJson('/api/scheduled-posts/upload-complete', ['media_storage_path' => 'x'])->assertUnauthorized();
    });

    it('isolamento: outro workspace com o próprio path válido recebe 404 e o post do primeiro não muda', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path);
        $other = createUserWithPermissions(['posts.create']);
        $otherPath = awFile($other, basename($path)); // mesmo nome de arquivo, prefixo do workspace dele
        $before = awSnapshot();

        awComplete($other, $otherPath)->assertNotFound();

        expect(awSnapshot())->toBe($before)
            ->and($post->fresh()->status)->toBe('awaiting_upload');
        Queue::assertNothingPushed();
    });

    it('isolamento: outro workspace usando o path do primeiro nunca libera o post (404 ou 422) e o banco não muda', function () {
        $path = awFile($this->user);
        awPost($this->user, $path);
        $other = createUserWithPermissions(['posts.create']);
        $before = awSnapshot();

        $response = awComplete($other, $path);

        expect($response->status())->toBeIn([404, 422])
            ->and(awSnapshot())->toBe($before);
        Queue::assertNothingPushed();
    });

    it('isolamento: o post do workspace B com o mesmo path literal não é liberado pelo usuário de A', function () {
        // Forja direta no banco: post de B com media_path sob o prefixo de A (não deveria existir, mas o escopo de tenant protege).
        $path = awFile($this->user);
        $other = createUserWithPermissions(['posts.create']);
        $foreign = awPost($other, $path);

        awComplete($this->user, $path)->assertNotFound();

        expect($foreign->fresh()->status)->toBe('awaiting_upload')
            ->and($foreign->fresh()->upload_completed_at)->toBeNull();
        Queue::assertNothingPushed();
    });
});

describe('cancelar awaiting_upload', function () {

    it('cancela, vira cancelled e apaga a mídia quando nenhum outro post a usa', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertOk();

        expect($post->fresh()->status)->toBe('cancelled');
        Storage::disk('s3')->assertMissing($path);
    });

    it('mantém a mídia quando outro post ainda a usa', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path);
        awPost($this->user, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertOk();

        Storage::disk('s3')->assertExists($path);
    });

    it('depois de cancelado, upload-complete não reativa o post', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path);
        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertOk();

        awComplete($this->user, $path);

        expect($post->fresh()->status)->toBe('cancelled');
        Queue::assertNothingPushed();
    });

    it('retorna 403 sem posts.delete e o post segue awaiting_upload', function () {
        $user = createUserWithPermissions(['posts.create']);
        $post = awPost($user, awFile($user));

        $this->withToken($user->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertForbidden();

        expect($post->fresh()->status)->toBe('awaiting_upload');
    });

    it('isolamento: post awaiting_upload de outro workspace retorna 404 e nada muda', function () {
        $other = createUserWithPermissions(['posts.create']);
        $path = awFile($other);
        $post = awPost($other, $path);

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertNotFound();

        expect($post->fresh()->status)->toBe('awaiting_upload');
        Storage::disk('s3')->assertExists($path);
    });
});

describe('evento ScheduledPostUpdated', function () {

    it('upload-complete emite o evento no canal privado do workspace com uuid e status pending', function () {
        Event::fake([ScheduledPostEvent::class]);
        $path = awFile($this->user);
        $post = awPost($this->user, $path);

        awComplete($this->user, $path)->assertOk();

        Event::assertDispatched(ScheduledPostEvent::class, function ($event) use ($post) {
            $channels = collect($event->broadcastOn())->map->name;

            return $event->broadcastAs() === 'ScheduledPostUpdated'
                && $channels->contains('private-workspaces.'.$this->ws)
                && $event->broadcastWith() === ['uuid' => $post->uuid, 'status' => 'pending'];
        });
    });

    it('cancelar emite o evento com status cancelled', function () {
        Event::fake([ScheduledPostEvent::class]);
        $post = awPost($this->user, awFile($this->user));

        $this->withToken($this->user->test_token)->deleteJson("/api/scheduled-posts/{$post->uuid}")->assertOk();

        Event::assertDispatched(ScheduledPostEvent::class, fn ($e) => $e->broadcastWith() === ['uuid' => $post->uuid, 'status' => 'cancelled']);
    });

    it('reservar (criação) e upload-complete sem posts não emitem evento', function () {
        Event::fake([ScheduledPostEvent::class]);

        awReserve($this->user, awFile($this->user, store: false))->assertCreated();
        awComplete($this->user, awFile($this->user, 'nada.mp4'))->assertNotFound();

        Event::assertNotDispatched(ScheduledPostEvent::class);
    });

    it('a expiração emite o evento com status failed', function () {
        Event::fake([ScheduledPostEvent::class]);
        $post = awPost($this->user, awFile($this->user));
        awOldCreated($post, 25);

        $this->artisan(app(ExpireAwaitingUploadsCommand::class)->getName())->assertSuccessful();

        Event::assertDispatched(ScheduledPostEvent::class, fn ($e) => $e->broadcastWith() === ['uuid' => $post->uuid, 'status' => 'failed']);
    });
});

describe('expiração de envios não concluídos', function () {

    function awExpire(): void
    {
        test()->artisan(app(ExpireAwaitingUploadsCommand::class)->getName())->assertSuccessful();
    }

    it('posts awaiting_upload além do limite viram failed com texto fixo e failed_at', function () {
        $post = awPost($this->user, awFile($this->user));
        awOldCreated($post, 25);

        awExpire();

        $post->refresh();
        expect($post->status)->toBe('failed')
            ->and($post->error_message)->toBe(AW_EXPIRED_TEXT)
            ->and($post->failed_at)->not->toBeNull();
        Queue::assertNothingPushed();
    });

    it('respeita o limite: 23h fica, 25h expira; só awaiting_upload é tocado', function () {
        $path = awFile($this->user);
        $recent = awPost($this->user, $path);
        awOldCreated($recent, 23);
        $old = awPost($this->user, $path);
        awOldCreated($old, 25);
        $pending = awPost($this->user, $path, ['status' => 'pending']);
        awOldCreated($pending, 48);
        $published = awPost($this->user, $path, ['status' => 'published']);
        awOldCreated($published, 48);

        awExpire();

        expect($recent->fresh()->status)->toBe('awaiting_upload')
            ->and($old->fresh()->status)->toBe('failed')
            ->and($pending->fresh()->status)->toBe('pending')
            ->and($published->fresh()->status)->toBe('published');
    });

    it('usa scheduling.upload_expiration_hours', function () {
        config(['scheduling.upload_expiration_hours' => 2]);
        $post = awPost($this->user, awFile($this->user));
        awOldCreated($post, 3);

        awExpire();

        expect($post->fresh()->status)->toBe('failed');
    });

    it('dispara a notificação de post falhado existente', function () {
        $post = awPost($this->user, awFile($this->user));
        awOldCreated($post, 25);

        awExpire();

        expect(Notification::where('type', 'post_failed')->count())->toBe(1);
    });

    it('depois de expirado, upload-complete não reativa o post', function () {
        $path = awFile($this->user);
        $post = awPost($this->user, $path);
        awOldCreated($post, 25);
        awExpire();

        awComplete($this->user, $path);

        expect($post->fresh()->status)->toBe('failed');
        Queue::assertNothingPushed();
    });

    it('está registrado na agenda', function () {
        $name = app(ExpireAwaitingUploadsCommand::class)->getName();

        $scheduled = collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains((string) $e->command, $name));

        expect($scheduled)->toBeTrue();
    });
});

describe('listagem e resource com o status novo', function () {

    it('GET /scheduled-posts devolve o post awaiting_upload com status e sem erro', function () {
        $post = awPost($this->user, awFile($this->user));

        $this->withToken($this->user->test_token)->getJson('/api/scheduled-posts')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $post->uuid, 'status' => 'awaiting_upload']);
    });

    it('GET /scheduled-posts?status=awaiting_upload filtra só os aguardando envio', function () {
        $path = awFile($this->user);
        $waiting = awPost($this->user, $path);
        awPost($this->user, $path, ['status' => 'pending']);

        $response = $this->withToken($this->user->test_token)->getJson('/api/scheduled-posts?status=awaiting_upload')->assertOk();

        expect(collect($response->json('data'))->pluck('uuid')->all())->toBe([$waiting->uuid]);
    });

    it('a listagem não mostra post awaiting_upload de outro workspace', function () {
        $other = createUserWithPermissions(['posts.create']);
        $foreign = awPost($other, awFile($other));

        $this->withToken($this->user->test_token)->getJson('/api/scheduled-posts')
            ->assertOk()
            ->assertJsonMissing(['uuid' => $foreign->uuid]);
    });
});
