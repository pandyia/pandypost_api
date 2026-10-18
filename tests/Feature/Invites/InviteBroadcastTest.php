<?php

use App\Events\InviteEvent;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function makeInvite(int $workspaceId, int $invitedBy, string $email): Invite
{
    $role = Role::withoutGlobalScopes()->create(['name' => 'Editor', 'workspace_id' => $workspaceId]);

    return Invite::withoutGlobalScopes()->create([
        'email' => $email,
        'workspace_id' => $workspaceId,
        'invited_by' => $invitedBy,
        'role_id' => $role->id,
        'status' => Invite::STATUS_PENDING,
        'expires_at' => now()->addDays(3),
    ]);
}

describe('broadcast de convites', function () {

    it('emite created no canal do workspace sem expor o e-mail', function () {
        $user = createUserWithoutPermissions();
        $workspaceId = $user->currentAccess->workspace_id;

        Event::fake([InviteEvent::class]);

        $invite = makeInvite($workspaceId, $user->id, 'novo@example.com');

        Event::assertDispatched(InviteEvent::class, function (InviteEvent $event) use ($invite, $workspaceId) {
            $channel = $event->broadcastOn()[0];
            $workspaceUuid = Workspace::withoutGlobalScopes()->whereKey($workspaceId)->value('uuid');

            return $event->action === 'created'
                && $channel instanceof PrivateChannel
                && $channel->name === 'private-workspaces.'.$workspaceUuid
                && $event->broadcastWith() === ['action' => 'created', 'invite_uuid' => $invite->uuid];
        });
    });

    it('emite accepted e declined quando o status muda', function () {
        $user = createUserWithoutPermissions();
        $workspaceId = $user->currentAccess->workspace_id;
        $invite = makeInvite($workspaceId, $user->id, 'novo@example.com');

        Event::fake([InviteEvent::class]);

        $invite->update(['status' => Invite::STATUS_ACCEPTED]);
        Event::assertDispatched(InviteEvent::class, fn ($e) => $e->action === 'accepted');

        $invite->update(['status' => Invite::STATUS_DECLINED]);
        Event::assertDispatched(InviteEvent::class, fn ($e) => $e->action === 'declined');
    });

    it('emite accepted e declined pelos endpoints reais', function () {
        $sender = createUserWithoutPermissions();
        $invitee = User::factory()->create();
        $workspaceId = $sender->currentAccess->workspace_id;
        $other = User::factory()->create();
        $accept = makeInvite($workspaceId, $sender->id, $invitee->email);
        $decline = makeInvite($workspaceId, $sender->id, $other->email);

        Event::fake([InviteEvent::class]);

        Sanctum::actingAs($other);
        $this->patchJson("/api/me/invites/{$decline->uuid}/decline")->assertOk();
        Event::assertDispatched(InviteEvent::class, fn ($e) => $e->action === 'declined' && $e->invite->is($decline));

        Sanctum::actingAs($invitee);
        $this->patchJson("/api/me/invites/{$accept->uuid}/accept")->assertOk();
        Event::assertDispatched(InviteEvent::class, fn ($e) => $e->action === 'accepted' && $e->invite->is($accept));
    });

    it('não emite quando o status não muda', function () {
        $user = createUserWithoutPermissions();
        $invite = makeInvite($user->currentAccess->workspace_id, $user->id, 'novo@example.com');

        Event::fake([InviteEvent::class]);

        $invite->update(['expires_at' => now()->addDays(5)]);

        Event::assertNotDispatched(InviteEvent::class);
    });

    it('também emite no canal pessoal do convidado, independente do aviso no sino', function () {
        $sender = createUserWithoutPermissions();
        $invitee = User::factory()->create();
        User::whereKey($invitee->id)->update(['notification_preferences' => json_encode(['in_app' => ['invite' => false]])]);

        Event::fake([InviteEvent::class]);

        makeInvite($sender->currentAccess->workspace_id, $sender->id, $invitee->email);

        Event::assertDispatched(InviteEvent::class, function (InviteEvent $event) use ($invitee) {
            $names = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            return in_array('private-App.Models.User.'.$invitee->id, $names, true) && count($names) === 2;
        });
    });

    it('sem conta do convidado, só o canal do workspace', function () {
        $user = createUserWithoutPermissions();

        Event::fake([InviteEvent::class]);

        makeInvite($user->currentAccess->workspace_id, $user->id, 'sem-conta@example.com');

        Event::assertDispatched(InviteEvent::class, fn (InviteEvent $event) => count($event->broadcastOn()) === 1);
    });
});
