<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\Platform;
use App\Exceptions\NotificationException;
use App\Models\Access;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\CentralNotification;
use App\Services\Factories\NotificationFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NotificationService
{
    private const POST_PERMISSIONS = ['posts.view', 'posts.create', 'posts.update', 'posts.delete'];

    private const BILLING_PERMISSIONS = ['billing.view', 'billing.manage'];

    private const UNREAD_LIST_LIMIT = 50;

    public function __construct(
        protected NotificationFactory $factory
    ) {}

    /**
     * Envia uma notificação a um usuário. Notificações da central são idempotentes
     * (tipo + recurso + destinatário). Exceções sobem para quem chamou.
     */
    public function send(User $user, NotificationType $type, array $payload = []): void
    {
        $notification = $this->factory->make($type, $payload);

        if ($notification instanceof CentralNotification && $this->alreadySent($user, $notification)) {
            return;
        }

        $user->notify($notification);
    }

    // ---------------------------------------------------------------
    // Ganchos de domínio — nunca derrubam o fluxo principal (RF-9)
    // ---------------------------------------------------------------

    public function notifyWorkspaceInvite(User $invitee, int $workspaceId, string $workspaceName, string $invitedByName, string $inviteUuid): void
    {
        $this->safely(function () use ($invitee, $workspaceId, $workspaceName, $invitedByName, $inviteUuid) {
            $this->send($invitee, NotificationType::WORKSPACE_INVITE, [
                'workspace_name' => $workspaceName,
                'invited_by_name' => $invitedByName,
                'workspace_id' => $workspaceId,
                'workspace_uuid' => Workspace::allAccess()->whereKey($workspaceId)->value('uuid'),
                'resource' => $inviteUuid,
            ]);
        });
    }

    public function notifyPostFailed(ScheduledPost $post): void
    {
        $this->safely(function () use ($post) {
            $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);

            if (! $account) {
                return;
            }

            $payload = [
                'resource' => $post->uuid,
                'post_title' => (string) $post->title,
                'platform' => $post->platform->value,
                'platform_label' => $post->platform->label(),
                'author_name' => User::withoutGlobalScopes()->whereKey($post->user_id)->value('name') ?? '',
                'workspace_id' => $account->workspace_id,
                'workspace_uuid' => Workspace::allAccess()->whereKey($account->workspace_id)->value('uuid'),
            ];

            foreach ($this->recipientsWithPermission($account->workspace_id, self::POST_PERMISSIONS) as $user) {
                $this->sendSafely($user, NotificationType::POST_FAILED, $payload);
            }
        });
    }

    /**
     * Opt-in: só o autor do post, e só se ligou a preferência. Apenas in-app.
     */
    public function notifyPostPublished(ScheduledPost $post): void
    {
        $this->safely(function () use ($post) {
            $author = User::withoutGlobalScopes()->find($post->user_id);

            if (! $author || ! $author->wantsPostPublished()) {
                return;
            }

            $account = SocialAccount::withoutGlobalScopes()->find($post->social_account_id);

            if (! $account) {
                return;
            }

            $this->send($author, NotificationType::POST_PUBLISHED, [
                'resource' => $post->uuid,
                'post_title' => (string) $post->title,
                'platform' => $post->platform->value,
                'platform_label' => $post->platform->label(),
                'workspace_id' => $account->workspace_id,
                'workspace_uuid' => Workspace::allAccess()->whereKey($account->workspace_id)->value('uuid'),
            ]);
        });
    }

    public function notifyBilling(NotificationType $type, Workspace $workspace, string $resource): void
    {
        $this->safely(function () use ($type, $workspace, $resource) {
            $payload = [
                'resource' => $resource,
                'workspace_id' => $workspace->id,
                'workspace_uuid' => $workspace->uuid,
                'workspace_name' => $workspace->name,
            ];

            foreach ($this->recipientsWithPermission($workspace->id, self::BILLING_PERMISSIONS) as $user) {
                $this->sendSafely($user, $type, $payload);
            }
        });
    }

    /**
     * Avisa quem conectou a conta. Nunca inclui a mensagem do erro da plataforma.
     */
    public function notifySocialAccountDisconnected(SocialAccount $account): void
    {
        $this->safely(function () use ($account) {
            $user = User::withoutGlobalScopes()->find($account->user_id);

            if (! $user) {
                return;
            }

            // O timestamp de expiração distingue uma nova desconexão após reconectar.
            $this->send($user, NotificationType::SOCIAL_ACCOUNT_DISCONNECTED, [
                'resource' => $account->uuid.':'.($account->expires_at?->timestamp ?? 0),
                'account_uuid' => $account->uuid,
                'nickname' => (string) $account->nickname,
                'platform' => $account->platform instanceof \BackedEnum ? $account->platform->value : (string) $account->platform,
                'platform_label' => Platform::tryFrom((string) $account->platform)?->label() ?? ucfirst((string) $account->platform),
                'workspace_id' => $account->workspace_id,
                'workspace_uuid' => Workspace::allAccess()->whereKey($account->workspace_id)->value('uuid'),
            ]);
        });
    }

    // ---------------------------------------------------------------
    // Consultas da central (usuário logado)
    // ---------------------------------------------------------------

    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->scopedQuery($user);

        match ($filters['read'] ?? 'all') {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => null,
        };

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->latest()->latest('id')->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function unreadCount(User $user): int
    {
        return $this->scopedQuery($user)->whereNull('read_at')->count();
    }

    public function unread(User $user): Collection
    {
        return $this->scopedQuery($user)->whereNull('read_at')->latest()->latest('id')->limit(self::UNREAD_LIST_LIMIT)->get();
    }

    public function markAsRead(User $user, string $id): Notification
    {
        $notification = $this->findForUser($user, $id);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return $notification->refresh();
    }

    public function markAllAsRead(User $user): int
    {
        return $this->scopedQuery($user)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function delete(User $user, string $id): void
    {
        $this->findForUser($user, $id)->delete();
    }

    public function findForUser(User $user, string $id): Notification
    {
        if (! Str::isUuid($id)) {
            throw NotificationException::notFound();
        }

        $notification = $user->notifications()->whereKey($id)->first();

        return $notification ?? throw NotificationException::notFound();
    }

    // ---------------------------------------------------------------
    // Preferências de e-mail
    // ---------------------------------------------------------------

    public function updatePreferences(User $user, array $data): array
    {
        $allowed = array_flip([...NotificationType::PREFERENCE_FAMILIES, 'post_published']);
        $merged = array_merge($user->notificationSettings(), array_intersect_key($data, $allowed));

        $user->forceFill(['notification_preferences' => array_map('boolval', $merged)])->save();

        return $user->notificationSettings();
    }

    // ---------------------------------------------------------------
    // Internos
    // ---------------------------------------------------------------

    /**
     * Notificações do workspace corrente do usuário + convites (que pertencem ao usuário).
     */
    private function scopedQuery(User $user): Builder
    {
        $workspaceId = $user->resolveCurrentAccess()?->workspace_id;

        return Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where(function (Builder $q) use ($workspaceId) {
                $q->where('type', NotificationType::WORKSPACE_INVITE->value);

                if ($workspaceId) {
                    $q->orWhere('workspace_id', $workspaceId);
                }
            });
    }

    /**
     * Membros do workspace cujo cargo tem alguma das permissões.
     */
    private function recipientsWithPermission(int $workspaceId, array $permissions): \Illuminate\Support\Collection
    {
        $roleIds = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->whereIn('permissions.name', $permissions)
            ->pluck('role_permission.role_id');

        $userIds = Access::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('role_id', $roleIds)
            ->pluck('user_id')
            ->unique();

        return User::withoutGlobalScopes()->whereIn('id', $userIds)->get();
    }

    private function alreadySent(User $user, CentralNotification $notification): bool
    {
        return $notification->dedupeKey !== null
            && $user->notifications()->where('dedupe_key', $notification->dedupeKey)->exists();
    }

    private function sendSafely(User $user, NotificationType $type, array $payload): void
    {
        $this->safely(fn () => $this->send($user, $type, $payload));
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('Falha ao notificar usuário: '.$e::class, ['error' => $e->getMessage()]);
        }
    }
}
