<?php

use App\Models\Invite;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function inviteWithNotification(User $sender, User $invitee): array
{
    $workspaceId = $sender->currentAccess->workspace_id;
    $role = Role::withoutGlobalScopes()->create(['name' => 'Editor', 'workspace_id' => $workspaceId]);

    $invite = Invite::withoutGlobalScopes()->create([
        'email' => $invitee->email,
        'workspace_id' => $workspaceId,
        'invited_by' => $sender->id,
        'role_id' => $role->id,
        'status' => Invite::STATUS_PENDING,
        'expires_at' => now()->addDays(3),
    ]);

    $notification = Notification::create([
        'id' => (string) Str::uuid(),
        'type' => 'workspace_invite',
        'notifiable_type' => $invitee->getMorphClass(),
        'notifiable_id' => $invitee->id,
        'workspace_id' => $invitee->currentAccess->workspace_id,
        'data' => [
            'type' => 'workspace_invite',
            'title' => 'Convite para um workspace',
            'message' => 'Você foi convidado.',
            'action_url' => '/settings/invites',
            'context' => ['invite_uuid' => $invite->uuid],
        ],
    ]);

    return [$invite, $notification];
}

describe('notificação de convite ao responder', function () {

    it('marca a notificação como lida ao recusar', function () {
        $sender = createUserWithoutPermissions();
        $invitee = createUserWithoutPermissions();
        [$invite, $notification] = inviteWithNotification($sender, $invitee);

        $this->withToken($invitee->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/decline")
            ->assertOk();

        expect($notification->fresh()->read_at)->not->toBeNull();
    });

    it('marca a notificação como lida ao aceitar', function () {
        $sender = createUserWithoutPermissions();
        $invitee = createUserWithoutPermissions();
        [$invite, $notification] = inviteWithNotification($sender, $invitee);

        $this->withToken($invitee->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/accept")
            ->assertOk();

        expect($notification->fresh()->read_at)->not->toBeNull();
    });

    it('não toca nas notificações de outros convites', function () {
        $sender = createUserWithoutPermissions();
        $invitee = createUserWithoutPermissions();
        [$invite] = inviteWithNotification($sender, $invitee);
        [, $other] = inviteWithNotification(createUserWithoutPermissions(), $invitee);

        $this->withToken($invitee->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/decline")
            ->assertOk();

        expect($other->fresh()->read_at)->toBeNull();
    });
});
