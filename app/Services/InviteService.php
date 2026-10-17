<?php

namespace App\Services;

use App\Exceptions\InviteException;
use App\Models\Access;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InviteService extends BaseService
{
    protected array $with = ['recipient', 'role', 'invitedBy', 'workspaceSender'];

    protected array $normalFilter = [];

    protected array $whereHas = [

        // pode ser também assim: 'uuid' => 'user'
        // Filtros de listagem(colunas inventadas) enviados, ex: colunas custom -> relation -> column
        'role_uuid' => ['role', 'uuid'],
        'workspace_uuid' => ['workspaceSender', 'uuid'],

        // Filtros de listagem(colunas inventadas) recebidos
        'sender' => ['invitedBy', 'email'],
        'recipient' => ['recipient', 'email'],
    ];

    public function __construct(
        Invite $invite,
        protected NotificationService $notificationService,
    ) {
        parent::__construct($invite);
    }

    public function receivedInvites(array $data)
    {
        return $this->paginate(
            $data,
            query: Invite::forRecipient(auth()->user()->email),
            with: ['roleSender', 'workspaceSender', 'invitedBy'],
        );
    }

    public function sendInvite(array $data): void
    {
        $user = auth()->user();
        $access = $user->resolveCurrentAccess();
        $workspaceId = $access->workspace_id;

        $alreadyInvited = Invite::pending()
            ->where('email', $data['email'])
            ->where('workspace_id', $workspaceId)
            ->exists();

        if ($alreadyInvited) {
            throw InviteException::alreadyInvited();
        }

        // Sem o escopo de workspace: o convidado ainda não é membro deste workspace.
        $invitedUser = User::withoutGlobalScopes()->where('email', $data['email'])->first();

        if ($invitedUser) {
            $alreadyMember = Access::where('user_id', $invitedUser->id)
                ->where('workspace_id', $workspaceId)
                ->exists();

            if ($alreadyMember) {
                throw InviteException::alreadyMember();
            }
        }

        DB::transaction(function () use ($data, $user, $access, $workspaceId, $invitedUser) {
            $role = Role::where('uuid', $data['role_uuid'])->firstOrFail();

            $invite = Invite::create([
                'email' => $data['email'],
                'workspace_id' => $workspaceId,
                'invited_by' => $user->id,
                'role_id' => $role->id,
                'status' => 'PENDING',
                'expires_at' => now()->addDays(config('app.invite_expiration_days', 3)), // ou invite expiration days
            ]);

            if ($invitedUser) {
                $this->notificationService->notifyWorkspaceInvite(
                    $invitedUser,
                    $workspaceId,
                    $access->workspace->name,
                    $user->name,
                    $invite->uuid,
                );
            }
        });
    }

    public function accept(Invite $invite): void
    {
        $this->authorizeInvite($invite);
        $this->ensureInviteIsPending($invite);

        $alreadyMember = Access::where('user_id', auth()->id())
            ->where('workspace_id', $invite->workspace_id)
            ->exists();

        if ($alreadyMember) {
            throw InviteException::alreadyMember();
        }

        DB::transaction(function () use ($invite) {
            // Revogar convites antigos aceitos para o mesmo email+workspace
            Invite::withoutGlobalScope('workspace')
                ->where('email', $invite->email)
                ->where('workspace_id', $invite->workspace_id)
                ->where('status', Invite::STATUS_ACCEPTED)
                ->where('id', '!=', $invite->id)
                ->update(['status' => Invite::STATUS_DECLINED]);
            Access::create([
                'user_id' => auth()->id(),
                'role_id' => $invite->role_id,
                'workspace_id' => $invite->workspace_id,
            ]);

            $invite->update(['status' => Invite::STATUS_ACCEPTED]);
        });
    }

    public function decline(Invite $invite): void
    {
        $this->authorizeInvite($invite);
        $this->ensureInviteIsPending($invite);

        $invite->update(['status' => Invite::STATUS_DECLINED]);
    }

    private function authorizeInvite(Invite $invite): void
    {
        if ($invite->email !== auth()->user()->email) {
            throw InviteException::notYourInvite();
        }
    }

    private function ensureInviteIsPending(Invite $invite): void
    {
        if (
            $invite->status !== Invite::STATUS_PENDING ||
            ($invite->expires_at && $invite->expires_at->isPast())
        ) {
            throw InviteException::inviteNotPending();
        }
    }
}
