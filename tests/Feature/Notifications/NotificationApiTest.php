<?php

use App\Console\Commands\PruneNotificationsCommand;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\CentralNotification;
use App\Notifications\Channels\WorkspaceDatabaseChannel;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function notifCreate(User $user, array $overrides = []): Notification
{
    $type = $overrides['type'] ?? 'post_failed';

    return Notification::create(array_merge([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'workspace_id' => $user->currentAccess->workspace_id,
        'data' => [
            'type' => $type,
            'title' => 'Falha ao publicar',
            'message' => 'Não foi possível publicar "Meu vídeo" no YouTube.',
            'action_url' => '/posts/abc',
            'context' => ['post_uuid' => 'abc'],
        ],
    ], $overrides));
}

describe('listar notificações', function () {
    it('lista só as do usuário logado com formato, paginação e unread_count', function () {
        $user = createUserWithoutPermissions();
        $other = createUserWithoutPermissions();
        notifCreate($user);
        notifCreate($user, ['read_at' => now()]);
        notifCreate($other);

        $this->withToken($user->test_token)->getJson('/api/notifications?per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [['id', 'type', 'title', 'message', 'action_url', 'context', 'read_at', 'created_at']],
                'links', 'meta', 'unread_count',
            ])
            ->assertJsonPath('data.0.context.post_uuid', 'abc');
    });

    it('filtra por read e por type', function () {
        $user = createUserWithoutPermissions();
        notifCreate($user);
        notifCreate($user, ['read_at' => now(), 'type' => 'payment_failed']);

        $this->withToken($user->test_token)->getJson('/api/notifications?read=unread')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'post_failed');
        $this->withToken($user->test_token)->getJson('/api/notifications?read=read')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'payment_failed');
        $this->withToken($user->test_token)->getJson('/api/notifications?type=payment_failed')
            ->assertOk()->assertJsonCount(1, 'data');
    });

    it('rejeita filtros inválidos com 422', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->getJson('/api/notifications?read=xpto&per_page=500&type=email_verification')
            ->assertStatus(422)->assertJsonValidationErrors(['read', 'per_page', 'type']);
    });

    it('exige autenticação (401)', function () {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread')->assertUnauthorized();
        $this->patchJson('/api/notifications/read-all')->assertUnauthorized();
        $this->getJson('/api/notification-preferences')->assertUnauthorized();
    });

    it('isola por workspace corrente, exceto convites', function () {
        $user = createUserWithoutPermissions();
        $wsA = $user->currentAccess->workspace_id;
        $wsB = Workspace::withoutGlobalScopes()->create(['name' => 'Outro WS', 'is_personal_team' => false]);
        $otherWs = Workspace::withoutGlobalScopes()->create(['name' => 'WS convidante', 'is_personal_team' => false]);

        notifCreate($user, ['workspace_id' => $wsA]);
        notifCreate($user, ['workspace_id' => $wsB->id, 'type' => 'payment_failed']);
        notifCreate($user, ['workspace_id' => $otherWs->id, 'type' => 'workspace_invite']);

        $types = collect($this->withToken($user->test_token)->getJson('/api/notifications')
            ->assertOk()->json('data'))->pluck('type')->sort()->values()->all();

        expect($types)->toBe(['post_failed', 'workspace_invite']);
    });

    it('unread devolve não lidas e total_unread', function () {
        $user = createUserWithoutPermissions();
        notifCreate($user);
        notifCreate($user, ['read_at' => now()]);

        $this->withToken($user->test_token)->getJson('/api/notifications/unread')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('total_unread', 1);
    });
});

describe('marcar como lida e remover', function () {
    it('marca uma como lida (idempotente)', function () {
        $user = createUserWithoutPermissions();
        $n = notifCreate($user);

        foreach ([1, 2] as $_) {
            $this->withToken($user->test_token)->patchJson("/api/notifications/{$n->id}/read")
                ->assertOk()
                ->assertJsonPath('message', 'Notificação marcada como lida.')
                ->assertJsonPath('data.id', $n->id);
        }

        expect($n->fresh()->read_at)->not->toBeNull();
    });

    it('marca todas do usuário como lidas sem tocar nas de outros', function () {
        $user = createUserWithoutPermissions();
        $other = createUserWithoutPermissions();
        notifCreate($user);
        notifCreate($user);
        $foreign = notifCreate($other);

        $this->withToken($user->test_token)->patchJson('/api/notifications/read-all')
            ->assertOk()->assertJsonPath('data.updated', 2);

        expect(Notification::where('notifiable_id', $user->id)->whereNull('read_at')->count())->toBe(0)
            ->and($foreign->fresh()->read_at)->toBeNull();
    });

    it('remove a notificação do usuário', function () {
        $user = createUserWithoutPermissions();
        $n = notifCreate($user);

        $this->withToken($user->test_token)->deleteJson("/api/notifications/{$n->id}")
            ->assertOk()->assertJsonPath('message', 'Notificação removida.');

        $this->assertDatabaseMissing('notifications', ['id' => $n->id]);
    });

    it('devolve 404 e não altera nada para notificação de outro usuário', function () {
        $user = createUserWithoutPermissions();
        $other = createUserWithoutPermissions();
        $foreign = notifCreate($other);

        $this->withToken($user->test_token)->patchJson("/api/notifications/{$foreign->id}/read")
            ->assertNotFound()->assertJsonPath('error', 'notification_not_found');
        $this->withToken($user->test_token)->deleteJson("/api/notifications/{$foreign->id}")->assertNotFound();
        $this->withToken($user->test_token)->patchJson('/api/notifications/nao-e-uuid/read')->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $foreign->id, 'read_at' => null]);
    });
});

describe('preferências de e-mail', function () {
    it('lê o padrão (tudo ligado) e altera por família', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->getJson('/api/notification-preferences')
            ->assertOk()->assertExactJson(['data' => ['invite' => true, 'posts' => true, 'social_accounts' => true, 'post_published' => true, 'in_app' => ['invite' => true, 'posts' => true, 'social_accounts' => true, 'post_published' => false]]]);

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['posts' => false])
            ->assertOk()->assertJsonPath('data.posts', false)->assertJsonPath('data.invite', true);

        expect($user->fresh()->emailPreferences()['posts'])->toBeFalse();
    });

    it('não permite desativar billing nem valores inválidos (422)', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['billing' => false])
            ->assertStatus(422)->assertJsonValidationErrors(['billing']);
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['posts' => 'talvez'])
            ->assertStatus(422)->assertJsonValidationErrors(['posts']);
    });

    it('não afeta as preferências de outro usuário', function () {
        $user = createUserWithoutPermissions();
        $other = createUserWithoutPermissions();

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['invite' => false])->assertOk();

        expect($other->fresh()->emailPreferences()['invite'])->toBeTrue();
    });
});

describe('retenção', function () {
    it('apaga lidas há mais de 30 dias e preserva as demais', function () {
        $user = createUserWithoutPermissions();
        $oldRead = notifCreate($user, ['read_at' => now()->subDays(31)]);
        $recentRead = notifCreate($user, ['read_at' => now()->subDays(5)]);
        $oldUnread = notifCreate($user, ['created_at' => now()->subDays(90)]);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $oldRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recentRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $oldUnread->id]);
        expect(PruneNotificationsCommand::RETENTION_DAYS)->toBe(30);
    });
});

describe('preferências de aviso no app', function () {
    it('altera o aviso no app por família sem mexer no e-mail', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['in_app' => ['posts' => false]])
            ->assertOk()
            ->assertJsonPath('data.in_app.posts', false)
            ->assertJsonPath('data.in_app.invite', true)
            ->assertJsonPath('data.posts', true);

        $fresh = $user->fresh();
        expect($fresh->inAppPreferences()['posts'])->toBeFalse()
            ->and($fresh->emailPreferences()['posts'])->toBeTrue();
    });

    it('alterar o e-mail preserva o aviso no app e vice-versa', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['in_app' => ['invite' => false]])->assertOk();
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['invite' => false])
            ->assertOk()->assertJsonPath('data.in_app.invite', false)->assertJsonPath('data.invite', false);
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['in_app' => ['social_accounts' => false]])
            ->assertOk()->assertJsonPath('data.invite', false)->assertJsonPath('data.in_app.invite', false);
    });

    it('rejeita billing e valores inválidos no aviso no app (422)', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['in_app' => ['billing' => false]])
            ->assertStatus(422)->assertJsonValidationErrors(['in_app.billing']);
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['in_app' => ['posts' => 'talvez']])
            ->assertStatus(422)->assertJsonValidationErrors(['in_app.posts']);
    });

    it('define os canais de entrega pela preferência (app × e-mail)', function () {
        $user = createUserWithoutPermissions();
        $invite = new CentralNotification(NotificationType::POST_FAILED, 't', 'm', '/x');
        $billing = new CentralNotification(NotificationType::PAYMENT_FAILED, 't', 'm', '/x');

        expect($invite->via($user))->toBe([WorkspaceDatabaseChannel::class, 'broadcast', 'mail']);

        notifSetPrefs($user, ['in_app' => ['posts' => false]]);
        expect($invite->via($user->fresh()))->toBe(['mail']);

        notifSetPrefs($user, ['posts' => false, 'in_app' => ['posts' => false]]);
        expect($invite->via($user->fresh()))->toBe([]);

        notifSetPrefs($user, ['posts' => false]);
        expect($invite->via($user->fresh()))->toBe([WorkspaceDatabaseChannel::class, 'broadcast']);

        notifSetPrefs($user, ['in_app' => ['posts' => false, 'invite' => false]]);
        expect($billing->via($user->fresh()))->toBe([WorkspaceDatabaseChannel::class, 'broadcast', 'mail']);
    });

    it('não grava a notificação quando o aviso no app está desligado', function () {
        $user = createUserWithoutPermissions();
        notifSetPrefs($user, ['posts' => false, 'in_app' => ['posts' => false]]);

        app(NotificationService::class)->send($user->fresh(), NotificationType::POST_FAILED, [
            'post_title' => 'Meu vídeo', 'platform_label' => 'YouTube', 'platform' => 'youtube',
            'author_name' => 'Ana', 'resource' => 'abc', 'workspace_id' => $user->currentAccess->workspace_id,
        ]);

        expect($user->notifications()->count())->toBe(0);
    });
});
