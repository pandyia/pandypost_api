<?php

use App\Enums\NotificationType;
use App\Models\Access;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\CentralNotification;
use App\Notifications\Channels\WorkspaceDatabaseChannel;
use App\Services\NotificationService;
use App\Services\WorkspaceService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function permNotif(User $user): Notification
{
    return Notification::create([
        'id' => (string) Str::uuid(),
        'type' => 'post_failed',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'workspace_id' => $user->currentAccess->workspace_id,
        'data' => ['type' => 'post_failed', 'title' => 'Falha', 'message' => 'Msg', 'action_url' => '/x', 'context' => []],
    ]);
}

describe('GET /api/notification-preferences', function () {
    it('retorna 403 sem notifications.view', function () {
        $user = createUserWithPermissions(['notifications.update']);

        $this->withToken($user->test_token)->getJson('/api/notification-preferences')->assertForbidden();
    });

    it('retorna 403 para usuário sem nenhuma permissão', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->getJson('/api/notification-preferences')->assertForbidden();
    });

    it('retorna 200 com notifications.view', function () {
        $user = createUserWithPermissions(['notifications.view']);

        $this->withToken($user->test_token)->getJson('/api/notification-preferences')
            ->assertOk()->assertJsonPath('data.posts', true);
    });

    it('retorna 401 sem autenticação', function () {
        $this->getJson('/api/notification-preferences')->assertUnauthorized();
    });
});

describe('PATCH /api/notification-preferences', function () {
    it('retorna 403 sem notifications.update e não altera o banco', function () {
        $user = createUserWithPermissions(['notifications.view']);
        $before = $user->fresh()->getRawOriginal('notification_preferences');

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['posts' => false])->assertForbidden();
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['in_app' => ['posts' => false]])->assertForbidden();

        expect($user->fresh()->getRawOriginal('notification_preferences'))->toBe($before)
            ->and($user->fresh()->emailPreferences()['posts'])->toBeTrue()
            ->and($user->fresh()->inAppPreferences()['posts'])->toBeTrue();
    });

    it('com view mas sem update: lê (200) e não altera (403)', function () {
        $user = createUserWithPermissions(['notifications.view']);

        $this->withToken($user->test_token)->getJson('/api/notification-preferences')->assertOk();
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['invite' => false])->assertForbidden();

        expect($user->fresh()->emailPreferences()['invite'])->toBeTrue();
    });

    it('altera com notifications.update e persiste', function () {
        $user = createUserWithPermissions(['notifications.update']);

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['posts' => false])
            ->assertOk()->assertJsonPath('data.posts', false);

        expect($user->fresh()->emailPreferences()['posts'])->toBeFalse();
    });

    it('valida o payload (422) para quem tem a permissão', function () {
        $user = createUserWithPermissions(['notifications.update']);

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['billing' => false])
            ->assertStatus(422)->assertJsonValidationErrors(['billing']);
    });

    it('retorna 401 sem autenticação', function () {
        $this->patchJson('/api/notification-preferences', ['posts' => false])->assertUnauthorized();
    });
});

describe('super admin e workspace corrente', function () {
    it('super admin acessa GET e PATCH sem a permissão', function () {
        $admin = createSuperAdmin();

        $this->withToken($admin->test_token)->getJson('/api/notification-preferences')->assertOk();
        $this->withToken($admin->test_token)->patchJson('/api/notification-preferences', ['posts' => false])->assertOk();

        expect($admin->fresh()->emailPreferences()['posts'])->toBeFalse();
    });

    it('avalia a permissão no workspace corrente, não em outro', function () {
        $user = createUserWithoutPermissions();
        $other = Workspace::withoutGlobalScopes()->create(['name' => 'Outro WS', 'is_personal_team' => false]);
        $roleOther = Role::withoutGlobalScopes()->create(['name' => 'Role B', 'workspace_id' => $other->id]);
        permission('notifications.view');
        permission('notifications.update');
        $roleOther->permissions()->sync(Permission::whereIn('name', ['notifications.view', 'notifications.update'])->pluck('id'));
        $accessOther = Access::create(['user_id' => $user->id, 'role_id' => $roleOther->id, 'workspace_id' => $other->id]);

        // workspace corrente continua sendo o original (sem permissões)
        $this->withToken($user->test_token)->getJson('/api/notification-preferences')->assertForbidden();
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['posts' => false])->assertForbidden();
        expect($user->fresh()->emailPreferences()['posts'])->toBeTrue();

        // trocando para o workspace com permissão, passa
        User::whereKey($user->id)->update(['access_id' => $accessOther->id]);
        // O guard guarda o usuário (e o currentAccess) entre requisições do mesmo teste; em produção cada request é novo.
        app('auth')->forgetGuards();
        $this->withToken($user->test_token)->getJson('/api/notification-preferences')->assertOk();
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['posts' => false])->assertOk();
    });
});

describe('recebimento continua livre', function () {
    it('rotas de recebimento respondem 200 sem as permissões', function () {
        $user = createUserWithoutPermissions();
        $a = permNotif($user);
        $b = permNotif($user);
        $c = permNotif($user);

        $this->withToken($user->test_token)->getJson('/api/notifications')->assertOk();
        $this->withToken($user->test_token)->getJson('/api/notifications/unread')->assertOk();
        $this->withToken($user->test_token)->patchJson("/api/notifications/{$a->id}/read")->assertOk();
        $this->withToken($user->test_token)->patchJson('/api/notifications/read-all')->assertOk();
        $this->withToken($user->test_token)->deleteJson("/api/notifications/{$c->id}")->assertOk();

        expect($a->fresh()->read_at)->not->toBeNull()
            ->and($b->fresh()->read_at)->not->toBeNull();
        $this->assertDatabaseMissing('notifications', ['id' => $c->id]);
    });

    it('send continua criando a notificação para usuário sem permissões', function () {
        $user = createUserWithoutPermissions();

        app(NotificationService::class)->send($user, NotificationType::POST_FAILED, [
            'post_title' => 'Meu vídeo', 'platform_label' => 'YouTube', 'platform' => 'youtube',
            'author_name' => 'Ana', 'resource' => 'abc', 'workspace_id' => $user->currentAccess->workspace_id,
        ]);

        expect($user->notifications()->count())->toBe(1);
    });

    it('usuário sem permissões continua recebendo pelos canais database, broadcast e e-mail', function () {
        Illuminate\Support\Facades\Notification::fake();
        $user = createUserWithoutPermissions();

        app(NotificationService::class)->send($user, NotificationType::POST_FAILED, [
            'post_title' => 'Meu vídeo', 'platform_label' => 'YouTube', 'platform' => 'youtube',
            'author_name' => 'Ana', 'resource' => 'abc', 'workspace_id' => $user->currentAccess->workspace_id,
        ]);

        Illuminate\Support\Facades\Notification::assertSentTo($user, CentralNotification::class, fn ($n, $channels) => in_array(WorkspaceDatabaseChannel::class, $channels, true)
            && in_array('broadcast', $channels, true)
            && in_array('mail', $channels, true));
    });
});

describe('catálogo de permissões', function () {
    it('seeder cria as duas permissões no grupo notifications', function () {
        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'notifications.view', 'description' => 'Visualizar preferências de notificações', 'group' => 'notifications']);
        $this->assertDatabaseHas('permissions', ['name' => 'notifications.update', 'description' => 'Alterar preferências de notificações', 'group' => 'notifications']);
    });

    it('perfil admin de workspace novo contém as duas permissões', function () {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();

        $workspace = app(WorkspaceService::class)->createAllAccess($user, ['name' => 'Novo WS']);

        $role = Role::withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
        expect($role->permissions()->whereIn('name', ['notifications.view', 'notifications.update'])->count())->toBe(2);
    });

    it('GET /api/permissions lista as duas novas', function () {
        $this->seed(PermissionSeeder::class);
        $user = createUserWithPermissions(['permissions.view']);

        $names = collect($this->withToken($user->test_token)->getJson('/api/permissions')->assertOk()->json('data'))->pluck('name');

        expect($names)->toContain('notifications.view', 'notifications.update');
    });
});
