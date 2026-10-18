<?php

use App\Jobs\ProcessStripeWebhookJob;
use App\Jobs\PublishPostJob;
use App\Models\Access;
use App\Models\Invite;
use App\Models\Notification;
use App\Models\Role;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\CentralNotification;
use App\Notifications\WorkspaceInviteAcceptedNotification;
use App\Notifications\WorkspaceInviteDeclinedNotification;
use App\Notifications\WorkspaceInviteNotification;
use App\Services\NotificationService;
use App\Services\Storage\StorageService;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification as NotificationFacade;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['cache.default' => 'array']);
});

function notifSetPrefs(User $user, array $prefs): void
{
    User::whereKey($user->id)->update(['notification_preferences' => json_encode($prefs)]);
}

function notifPost(User $author, array $overrides = []): ScheduledPost
{
    $account = connectedSocialAccount($author, 'youtube');

    return ScheduledPost::withoutGlobalScopes()->create(array_merge([
        'user_id' => $author->id,
        'social_account_id' => $account->id,
        'platform' => 'youtube',
        'title' => 'Meu vídeo',
        'status' => 'pending',
    ], $overrides));
}

function notifFailPost(ScheduledPost $post): void
{
    $storage = Mockery::mock(StorageService::class);
    $storage->shouldReceive('deleteMany')->andReturn(true);
    app()->instance(StorageService::class, $storage);

    (new PublishPostJob($post))->failed(new Exception('Segredo do Google: ya29.token-vazado'));
}

describe('post_failed', function () {
    it('notifica todos os membros com permissão de posts, com author_name, e ninguém sem permissão', function () {
        $author = createUserWithPermissions(['posts.create']);
        $workspace = Workspace::withoutGlobalScopes()->find($author->currentAccess->workspace_id);
        $manager = addUserToWorkspace($workspace);
        $manager->currentAccess->role->permissions()->sync([permission('posts.view')->id]);
        $semPermissao = addUserToWorkspace($workspace);
        $post = notifPost($author);

        notifFailPost($post);

        $rows = Notification::where('type', 'post_failed')->get();
        expect($rows->pluck('notifiable_id')->sort()->values()->all())->toBe(collect([$author->id, $manager->id])->sort()->values()->all());

        $data = $rows->first()->data;
        expect($data['context']['author_name'])->toBe($author->name)
            ->and($data['context']['post_uuid'])->toBe($post->uuid)
            ->and($data['context']['workspace_uuid'])->toBe($workspace->uuid)
            ->and($data['action_url'])->toBe('/pipeline')
            ->and(json_encode($data))->not->toContain('ya29')->not->toContain('token')
            ->and($rows->first()->workspace_id)->toBe($workspace->id);
    });

    it('não duplica ao reexecutar failed()', function () {
        $author = createUserWithPermissions(['posts.create']);
        $post = notifPost($author);

        notifFailPost($post);
        notifFailPost($post);

        expect(Notification::where('type', 'post_failed')->count())->toBe(1);
    });

    it('não notifica membros de outro workspace', function () {
        $author = createUserWithPermissions(['posts.create']);
        $outsider = createUserWithPermissions(['posts.view', 'posts.create']);
        $post = notifPost($author);

        notifFailPost($post);

        expect(Notification::where('notifiable_id', $outsider->id)->count())->toBe(0);
    });

    it('respeita a preferência de e-mail da família posts', function () {
        NotificationFacade::fake();
        $author = createUserWithPermissions(['posts.create']);
        notifSetPrefs($author, ['posts' => false]);
        $post = notifPost($author);

        notifFailPost($post);

        NotificationFacade::assertSentTo($author, CentralNotification::class, function ($n, $channels) {
            return in_array('broadcast', $channels, true) && ! in_array('mail', $channels, true);
        });
    });

    it('com e-mail ligado envia mail, database e broadcast', function () {
        NotificationFacade::fake();
        $author = createUserWithPermissions(['posts.create']);
        $post = notifPost($author);

        notifFailPost($post);

        NotificationFacade::assertSentTo($author, CentralNotification::class, fn ($n, $channels) => in_array('mail', $channels, true)
            && in_array('broadcast', $channels, true)
            && count($channels) === 3);
    });

    it('falha ao notificar não derruba o fluxo (post fica failed)', function () {
        $author = createUserWithPermissions(['posts.create']);
        $post = notifPost($author);
        NotificationFacade::shouldReceive('send')->andThrow(new RuntimeException('broker fora'));
        NotificationFacade::shouldReceive('sendNow')->andThrow(new RuntimeException('broker fora'));

        notifFailPost($post);

        expect($post->fresh()->status)->toBe('failed');
    });
});

describe('billing', function () {
    function notifBillingWorkspace(): array
    {
        $owner = createUserWithPermissions(['billing.manage']);
        $workspace = Workspace::withoutGlobalScopes()->find($owner->currentAccess->workspace_id);
        $workspace->forceFill(['stripe_id' => 'cus_notif_1'])->save();
        $viewer = addUserToWorkspace($workspace);
        $viewer->currentAccess->role->permissions()->sync([permission('billing.view')->id]);
        $semBilling = addUserToWorkspace($workspace);

        return [$owner, $viewer, $semBilling, $workspace];
    }

    function notifInvoiceFailed(string $eventId): array
    {
        return [
            'id' => $eventId,
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'id' => 'in_notif_1', 'customer' => 'cus_notif_1', 'amount_due' => 1000, 'currency' => 'brl',
            ]],
        ];
    }

    it('payment_failed notifica membros com permissão de billing, sem duplicar no mesmo webhook reentregue', function () {
        [$owner, $viewer, $semBilling] = notifBillingWorkspace();

        ProcessStripeWebhookJob::dispatchSync(notifInvoiceFailed('evt_1'));
        ProcessStripeWebhookJob::dispatchSync(notifInvoiceFailed('evt_2')); // evento distinto, mesma invoice

        $ids = Notification::where('type', 'payment_failed')->pluck('notifiable_id')->sort()->values()->all();
        expect($ids)->toBe(collect([$owner->id, $viewer->id])->sort()->values()->all())
            ->and(Notification::where('notifiable_id', $semBilling->id)->count())->toBe(0);
    });

    it('billing sempre envia e-mail, mesmo com todas as preferências desligadas', function () {
        NotificationFacade::fake();
        [$owner] = notifBillingWorkspace();
        notifSetPrefs($owner, ['invite' => false, 'posts' => false, 'social_accounts' => false]);

        ProcessStripeWebhookJob::dispatchSync(notifInvoiceFailed('evt_3'));

        NotificationFacade::assertSentTo($owner, CentralNotification::class, fn ($n, $channels) => in_array('mail', $channels, true));
    });

    function notifSubscriptionEvent(string $eventId, string $type, array $object = [], array $previous = []): array
    {
        return [
            'id' => $eventId,
            'type' => $type,
            'data' => array_filter([
                'object' => array_merge(['id' => 'sub_notif_1', 'customer' => 'cus_notif_1'], $object),
                'previous_attributes' => $previous ?: null,
            ]),
        ];
    }

    it('subscription.deleted notifica quem tem permissão de billing, sem duplicar', function () {
        [$owner, $viewer, $semBilling] = notifBillingWorkspace();

        ProcessStripeWebhookJob::dispatchSync(notifSubscriptionEvent('evt_c1', 'customer.subscription.deleted'));
        ProcessStripeWebhookJob::dispatchSync(notifSubscriptionEvent('evt_c2', 'customer.subscription.deleted')); // evento distinto, mesma assinatura

        $ids = Notification::where('type', 'subscription_canceled')->pluck('notifiable_id')->sort()->values()->all();
        expect($ids)->toBe(collect([$owner->id, $viewer->id])->sort()->values()->all())
            ->and(Notification::where('notifiable_id', $semBilling->id)->count())->toBe(0);
    });

    it('subscription.updated notifica só quando o cancelamento é agendado, e o fim do período não repete', function () {
        [$owner] = notifBillingWorkspace();

        // atualização qualquer: nada
        ProcessStripeWebhookJob::dispatchSync(notifSubscriptionEvent('evt_u1', 'customer.subscription.updated', ['cancel_at_period_end' => false], ['status' => 'trialing']));
        expect(Notification::where('type', 'subscription_canceled')->count())->toBe(0);

        // cancelamento agendado: avisa
        ProcessStripeWebhookJob::dispatchSync(notifSubscriptionEvent('evt_u2', 'customer.subscription.updated', ['cancel_at_period_end' => true], ['cancel_at_period_end' => false]));
        expect(Notification::where('type', 'subscription_canceled')->where('notifiable_id', $owner->id)->count())->toBe(1);

        // outra atualização já com cancelamento agendado e o fim do período: continua 1
        ProcessStripeWebhookJob::dispatchSync(notifSubscriptionEvent('evt_u3', 'customer.subscription.updated', ['cancel_at_period_end' => true], ['status' => 'active']));
        ProcessStripeWebhookJob::dispatchSync(notifSubscriptionEvent('evt_u4', 'customer.subscription.deleted'));
        expect(Notification::where('type', 'subscription_canceled')->where('notifiable_id', $owner->id)->count())->toBe(1);
    });

    it('subscription.deleted de cliente desconhecido é ignorado', function () {
        notifBillingWorkspace();

        ProcessStripeWebhookJob::dispatchSync(notifSubscriptionEvent('evt_u5', 'customer.subscription.deleted', ['customer' => 'cus_outro']));

        expect(Notification::where('type', 'subscription_canceled')->count())->toBe(0);
    });
});

describe('social_account_disconnected', function () {
    it('notifica quem conectou a conta quando o token expirou sem refresh_token, sem vazar o erro', function () {
        $user = createUserWithPermissions([]);
        $account = connectedSocialAccount($user, 'youtube', [
            'access_token' => json_encode(['access_token' => 'segredo-x', 'created' => time() - 7200, 'expires_in' => 3600]),
            'refresh_token' => null,
            'nickname' => 'Meu canal',
        ]);

        $method = new ReflectionMethod(YouTubeService::class, 'getAuthenticatedClient');
        $service = new YouTubeService(Mockery::mock(StorageService::class));

        expect(fn () => $method->invoke($service, $account))->toThrow(Exception::class);

        $n = Notification::where('type', 'social_account_disconnected')->first();
        expect($n)->not->toBeNull()
            ->and($n->notifiable_id)->toBe($user->id)
            ->and($n->data['context']['social_account_uuid'])->toBe($account->uuid)
            ->and(json_encode($n->data))->not->toContain('segredo-x')->not->toContain('refresh_token');
    });
});

describe('workspace_invite', function () {
    it('convite para usuário com conta cria notificação na central e respeita preferência de e-mail', function () {
        NotificationFacade::fake();
        $user = createUserWithPermissions(['invites.create']);
        $role = Role::withoutGlobalScopes()->create(['name' => 'Cargo', 'workspace_id' => $user->currentAccess->workspace_id]);
        $invited = User::factory()->create(['email' => 'convidado@teste.com']);
        notifSetPrefs($invited, ['invite' => false]);

        $this->withToken($user->test_token)->postJson('/api/invites', ['email' => 'convidado@teste.com', 'role_uuid' => $role->uuid])
            ->assertOk();

        NotificationFacade::assertSentTo($invited, WorkspaceInviteNotification::class, function ($n, $channels) use ($user) {
            return $n->workspaceId === $user->currentAccess->workspace_id
                && ! in_array('mail', $channels, true)
                && in_array('broadcast', $channels, true);
        });
    });

    it('persiste a notificação, sem duplicar, e e-mail sem conta não gera notificação', function () {
        $user = createUserWithPermissions(['invites.create']);
        $role = Role::withoutGlobalScopes()->create(['name' => 'Cargo', 'workspace_id' => $user->currentAccess->workspace_id]);
        $invited = User::factory()->create(['email' => 'convidado@teste.com']);

        $this->withToken($user->test_token)->postJson('/api/invites', ['email' => 'convidado@teste.com', 'role_uuid' => $role->uuid])->assertOk();
        $this->withToken($user->test_token)->postJson('/api/invites', ['email' => 'semconta@teste.com', 'role_uuid' => $role->uuid])->assertOk();

        $invite = Invite::where('email', 'convidado@teste.com')->first();
        $n = Notification::where('type', 'workspace_invite')->get();
        expect($n)->toHaveCount(1)
            ->and($n[0]->notifiable_id)->toBe($invited->id)
            ->and($n[0]->workspace_id)->toBe($user->currentAccess->workspace_id)
            ->and($n[0]->data['context']['invite_uuid'])->toBe($invite->uuid);
    });

    it('falha ao notificar não impede o convite', function () {
        $user = createUserWithPermissions(['invites.create']);
        $role = Role::withoutGlobalScopes()->create(['name' => 'Cargo', 'workspace_id' => $user->currentAccess->workspace_id]);
        User::factory()->create(['email' => 'convidado@teste.com']);
        NotificationFacade::shouldReceive('send')->andThrow(new RuntimeException('broker fora'));
        NotificationFacade::shouldReceive('sendNow')->andThrow(new RuntimeException('broker fora'));

        $this->withToken($user->test_token)->postJson('/api/invites', ['email' => 'convidado@teste.com', 'role_uuid' => $role->uuid])
            ->assertOk();

        $this->assertDatabaseHas('invites', ['email' => 'convidado@teste.com']);
    });
});

function notifPendingInvite(User $inviter, User $invitee): Invite
{
    $role = Role::withoutGlobalScopes()->create(['name' => 'Cargo', 'workspace_id' => $inviter->currentAccess->workspace_id]);

    return Invite::withoutGlobalScopes()->create([
        'email' => $invitee->email,
        'workspace_id' => $inviter->currentAccess->workspace_id,
        'invited_by' => $inviter->id,
        'role_id' => $role->id,
        'status' => Invite::STATUS_PENDING,
        'expires_at' => now()->addDays(3),
    ]);
}

describe('workspace_invite_accepted', function () {
    it('notifica quem convidou, com o contrato esperado, sem duplicar e sem vazar segredo', function () {
        $inviter = createUserWithPermissions(['invites.create']);
        $invitee = User::factory()->create(['name' => 'Maria Souza']);
        $invite = notifPendingInvite($inviter, $invitee);
        $workspace = Workspace::allAccess()->find($inviter->currentAccess->workspace_id);

        $this->withToken($invitee->test_token ?? $invitee->createToken('t')->plainTextToken)
            ->patchJson("/api/me/invites/{$invite->uuid}/accept")->assertOk();

        $n = Notification::where('type', 'workspace_invite_accepted')->get();
        expect($n)->toHaveCount(1)
            ->and($n[0]->notifiable_id)->toBe($inviter->id)
            ->and($n[0]->workspace_id)->toBe($workspace->id)
            ->and($n[0]->data)->toBe([
                'type' => 'workspace_invite_accepted',
                'title' => 'Convite aceito',
                'message' => "Maria Souza aceitou seu convite para o workspace {$workspace->name}.",
                'action_url' => '/settings/invites',
                'context' => [
                    'workspace_uuid' => $workspace->uuid,
                    'invite_uuid' => $invite->uuid,
                    'accepted_by_uuid' => $invitee->uuid,
                ],
            ]);

        // segunda chamada não duplica
        app(NotificationService::class)->notifyWorkspaceInviteAccepted($invite, $invitee);
        expect(Notification::where('type', 'workspace_invite_accepted')->count())->toBe(1)
            ->and(json_encode($n[0]->data))->not->toContain($invitee->email);
    });

    it('não notifica o convidado nem outros membros', function () {
        $inviter = createUserWithPermissions(['invites.create']);
        $other = addUserToWorkspace(Workspace::allAccess()->find($inviter->currentAccess->workspace_id));
        $invitee = User::factory()->create();
        $invite = notifPendingInvite($inviter, $invitee);

        app(NotificationService::class)->notifyWorkspaceInviteAccepted($invite, $invitee);

        expect(Notification::where('type', 'workspace_invite_accepted')->pluck('notifiable_id')->all())->toBe([$inviter->id])
            ->and($other->notifications()->count())->toBe(0);
    });

    it('não notifica se o remetente saiu do workspace ou não existe mais', function () {
        $inviter = createUserWithPermissions(['invites.create']);
        $invitee = User::factory()->create();
        $invite = notifPendingInvite($inviter, $invitee);
        Access::where('user_id', $inviter->id)->where('workspace_id', $invite->workspace_id)->delete();

        app(NotificationService::class)->notifyWorkspaceInviteAccepted($invite, $invitee);
        $invite->invited_by = 999999;
        app(NotificationService::class)->notifyWorkspaceInviteAccepted($invite, $invitee);

        expect(Notification::where('type', 'workspace_invite_accepted')->count())->toBe(0);
    });

    it('respeita a preferência da família invite e renderiza o e-mail com o link', function () {
        NotificationFacade::fake();
        $inviter = createUserWithPermissions(['invites.create']);
        $invitee = User::factory()->create(['name' => 'Maria <b>Souza</b>']);
        $invite = notifPendingInvite($inviter, $invitee);

        app(NotificationService::class)->notifyWorkspaceInviteAccepted($invite, $invitee);
        NotificationFacade::assertSentTo($inviter, WorkspaceInviteAcceptedNotification::class, function ($n, $channels) use ($inviter) {
            $html = $n->toMail($inviter)->render();

            return in_array('mail', $channels, true)
                && str_contains($html, '/settings/invites')
                && str_contains($html, 'Maria &lt;b&gt;Souza&lt;/b&gt;');
        });

        $other = createUserWithPermissions(['invites.create']);
        notifSetPrefs($other, ['invite' => false]);
        $invite2 = notifPendingInvite($other, $invitee);
        app(NotificationService::class)->notifyWorkspaceInviteAccepted($invite2, $invitee);
        NotificationFacade::assertSentTo($other, WorkspaceInviteAcceptedNotification::class, fn ($n, $channels) => ! in_array('mail', $channels, true) && in_array('broadcast', $channels, true));
    });

    it('falha ao notificar não impede o aceite', function () {
        $inviter = createUserWithPermissions(['invites.create']);
        $invitee = User::factory()->create();
        $invite = notifPendingInvite($inviter, $invitee);
        NotificationFacade::shouldReceive('send')->andThrow(new RuntimeException('broker fora'));
        NotificationFacade::shouldReceive('sendNow')->andThrow(new RuntimeException('broker fora'));

        $this->withToken($invitee->createToken('t')->plainTextToken)
            ->patchJson("/api/me/invites/{$invite->uuid}/accept")->assertOk();

        expect($invite->fresh()->status)->toBe(Invite::STATUS_ACCEPTED);
    });
});

describe('workspace_invite_declined', function () {
    it('notifica quem convidou, com o contrato esperado, sem duplicar e sem vazar segredo', function () {
        $inviter = createUserWithPermissions(['invites.create']);
        $invitee = User::factory()->create(['name' => 'Maria Souza']);
        $invite = notifPendingInvite($inviter, $invitee);
        $workspace = Workspace::allAccess()->find($inviter->currentAccess->workspace_id);

        $this->withToken($invitee->test_token ?? $invitee->createToken('t')->plainTextToken)
            ->patchJson("/api/me/invites/{$invite->uuid}/decline")->assertOk();

        $n = Notification::where('type', 'workspace_invite_declined')->get();
        expect($n)->toHaveCount(1)
            ->and($n[0]->notifiable_id)->toBe($inviter->id)
            ->and($n[0]->workspace_id)->toBe($workspace->id)
            ->and($n[0]->data)->toBe([
                'type' => 'workspace_invite_declined',
                'title' => 'Convite recusado',
                'message' => "Maria Souza recusou seu convite para o workspace {$workspace->name}.",
                'action_url' => '/settings/invites',
                'context' => [
                    'workspace_uuid' => $workspace->uuid,
                    'invite_uuid' => $invite->uuid,
                    'declined_by_uuid' => $invitee->uuid,
                ],
            ]);

        // segunda chamada não duplica
        app(NotificationService::class)->notifyWorkspaceInviteDeclined($invite, $invitee);
        expect(Notification::where('type', 'workspace_invite_declined')->count())->toBe(1)
            ->and(json_encode($n[0]->data))->not->toContain($invitee->email);
    });

    it('não notifica o convidado nem outros membros', function () {
        $inviter = createUserWithPermissions(['invites.create']);
        $other = addUserToWorkspace(Workspace::allAccess()->find($inviter->currentAccess->workspace_id));
        $invitee = User::factory()->create();
        $invite = notifPendingInvite($inviter, $invitee);

        app(NotificationService::class)->notifyWorkspaceInviteDeclined($invite, $invitee);

        expect(Notification::where('type', 'workspace_invite_declined')->pluck('notifiable_id')->all())->toBe([$inviter->id])
            ->and($other->notifications()->count())->toBe(0);
    });

    it('não notifica se o remetente saiu do workspace ou não existe mais', function () {
        $inviter = createUserWithPermissions(['invites.create']);
        $invitee = User::factory()->create();
        $invite = notifPendingInvite($inviter, $invitee);
        Access::where('user_id', $inviter->id)->where('workspace_id', $invite->workspace_id)->delete();

        app(NotificationService::class)->notifyWorkspaceInviteDeclined($invite, $invitee);
        $invite->invited_by = 999999;
        app(NotificationService::class)->notifyWorkspaceInviteDeclined($invite, $invitee);

        expect(Notification::where('type', 'workspace_invite_declined')->count())->toBe(0);
    });

    it('respeita a preferência da família invite e renderiza o e-mail com o link', function () {
        NotificationFacade::fake();
        $inviter = createUserWithPermissions(['invites.create']);
        $invitee = User::factory()->create(['name' => 'Maria <b>Souza</b>']);
        $invite = notifPendingInvite($inviter, $invitee);

        app(NotificationService::class)->notifyWorkspaceInviteDeclined($invite, $invitee);
        NotificationFacade::assertSentTo($inviter, WorkspaceInviteDeclinedNotification::class, function ($n, $channels) use ($inviter) {
            $html = $n->toMail($inviter)->render();

            return in_array('mail', $channels, true)
                && str_contains($html, '/settings/invites')
                && str_contains($html, 'Maria &lt;b&gt;Souza&lt;/b&gt;');
        });

        $other = createUserWithPermissions(['invites.create']);
        notifSetPrefs($other, ['invite' => false]);
        $invite2 = notifPendingInvite($other, $invitee);
        app(NotificationService::class)->notifyWorkspaceInviteDeclined($invite2, $invitee);
        NotificationFacade::assertSentTo($other, WorkspaceInviteDeclinedNotification::class, fn ($n, $channels) => ! in_array('mail', $channels, true) && in_array('broadcast', $channels, true));
    });

    it('falha ao notificar não impede a recusa', function () {
        $inviter = createUserWithPermissions(['invites.create']);
        $invitee = User::factory()->create();
        $invite = notifPendingInvite($inviter, $invitee);
        NotificationFacade::shouldReceive('send')->andThrow(new RuntimeException('broker fora'));
        NotificationFacade::shouldReceive('sendNow')->andThrow(new RuntimeException('broker fora'));

        $this->withToken($invitee->createToken('t')->plainTextToken)
            ->patchJson("/api/me/invites/{$invite->uuid}/decline")->assertOk();

        expect($invite->fresh()->status)->toBe(Invite::STATUS_DECLINED);
    });
});

describe('post_published (opt-in)', function () {
    it('desligado por padrão não cria notificação', function () {
        $author = createUserWithPermissions(['posts.create']);
        $post = notifPost($author);

        $post->update(['status' => 'published']);

        expect(Notification::where('type', 'post_published')->count())->toBe(0);
    });

    it('ligado cria só para o autor, in-app, sem duplicar', function () {
        NotificationFacade::fake();
        $author = createUserWithPermissions(['posts.create']);
        $manager = addUserToWorkspace(Workspace::withoutGlobalScopes()->find($author->currentAccess->workspace_id));
        $manager->currentAccess->role->permissions()->sync([permission('posts.view')->id]);
        notifSetPrefs($author, ['post_published' => true]);
        notifSetPrefs($manager, ['post_published' => true]);
        $post = notifPost($author);

        $post->update(['status' => 'published']);

        NotificationFacade::assertSentTo($author, CentralNotification::class, fn ($n, $channels) => ! in_array('mail', $channels, true)
            && in_array('broadcast', $channels, true));
        NotificationFacade::assertNotSentTo($manager, CentralNotification::class);
    });

    it('persiste com o contrato e não duplica em novas atualizações', function () {
        $author = createUserWithPermissions(['posts.create']);
        notifSetPrefs($author, ['post_published' => true]);
        $post = notifPost($author);

        $post->update(['status' => 'published']);
        $post->update(['status' => 'processing']);
        $post->update(['status' => 'published']);

        $rows = Notification::where('type', 'post_published')->get();
        expect($rows)->toHaveCount(1)
            ->and($rows[0]->data['title'])->toBe('Post publicado')
            ->and($rows[0]->data['message'])->toContain('Meu vídeo')->toContain('YouTube')
            ->and($rows[0]->data['action_url'])->toBe('/pipeline')
            ->and($rows[0]->data['context']['post_uuid'])->toBe($post->uuid)
            ->and($rows[0]->workspace_id)->toBe($author->currentAccess->workspace_id);
    });

    it('falha ao notificar não derruba a publicação', function () {
        $author = createUserWithPermissions(['posts.create']);
        notifSetPrefs($author, ['post_published' => true]);
        $post = notifPost($author);
        NotificationFacade::shouldReceive('send')->andThrow(new RuntimeException('broker fora'));
        NotificationFacade::shouldReceive('sendNow')->andThrow(new RuntimeException('broker fora'));

        $post->update(['status' => 'published']);

        expect($post->fresh()->status)->toBe('published');
    });

    it('valida e altera a preferência via API', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['post_published' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['post_published']);
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['post_published' => true])
            ->assertOk()->assertJsonPath('data.post_published', true);
        $this->withToken($user->test_token)->getJson('/api/notification-preferences')
            ->assertJsonPath('data.post_published', true)->assertJsonPath('data.posts', true);
    });
});
