<?php

namespace App\Models;

use App\Models\Traits\BelongsToWorkspace;
use App\Observers\RoleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

#[ObservedBy(RoleObserver::class)]
class Role extends Model implements Auditable
{
    use BelongsToWorkspace, AuditableTrait;

    public function generateTags(): array
    {
        return ['role'];
    }

    protected $fillable = ['name', 'uuid', 'workspace_id'];

    protected $hidden = [
        'id',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted()
    {
        static::creating(fn($role) => $role->uuid = $role->uuid ?: (string) Str::uuid());
    }

    // Relationships

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(Access::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Access::class, 'role_id', 'id', 'id', 'user_id');
    }

    // Business Methods

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    public static function adminPermissions(): array
    {
        return Permission::pluck('id')->toArray();
    }

    public static function isRoleNameAlreadyExists(string $name, int $workspaceId): bool
    {
        return self::where('name', $name)
            ->where('workspace_id', $workspaceId)
            ->exists();
    }
}

