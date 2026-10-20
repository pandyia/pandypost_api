<?php

namespace App\Models;

use App\Models\Traits\BelongsToWorkspace;
use App\Models\Traits\HasUuid;
use App\Observers\InviteObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

#[ObservedBy(InviteObserver::class)]
class Invite extends Model implements Auditable
{
    use AuditableTrait, BelongsToWorkspace, HasFactory, HasUuid;

    public function generateTags(): array
    {
        return ['invite'];
    }

    protected $fillable = [
        'email',
        'workspace_id',
        'invited_by',
        'role_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['id'];

    const STATUS_PENDING = 'PENDING';

    const STATUS_ACCEPTED = 'ACCEPTED';

    const STATUS_DECLINED = 'DECLINED';

    const STATUS_EXPIRED = 'EXPIRED';

    // funciona AUTOMÁTICO apenas quando usado model como parametro no controller (Invite $invite, usado para accept e decline)
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope('workspace')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workspaceSender(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id')->withoutGlobalScope('member');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function roleSender(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id')->withoutGlobalScope('workspace');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired($query)
    {
        return $query->pending()->where('expires_at', '<', now());
    }

    public function scopeForRecipient($query, string $email)
    {
        return $query->withoutGlobalScope('workspace')->where('email', $email);
    }

    public function scopeExpiredDeletable($query)
    {
        return $query->where('status', self::STATUS_EXPIRED)
            ->where('expires_at', '<', now()->subDays(30));
    }
}
