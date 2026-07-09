<?php

namespace App\Models;

use App\Observers\WorkspaceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

#[ObservedBy(WorkspaceObserver::class)]
class Workspace extends Model implements Auditable
{
    use AuditableTrait, Billable;

    protected $fillable = ['name', 'is_personal_team', 'uuid'];

    public function getAuditRepresentation(): string
    {
        return $this->name;
    }

    // tags para auditoria
    public function generateTags(): array
    {
        return ['workspace'];
    }

    protected $hidden = [
        'id',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted()
    {
        // sempre que for buscar workspaces, buscar apenas os que o usuário tem acesso  
        static::addGlobalScope('member', function ($builder) {
            if (auth()->hasUser()) {
                $builder->whereHas('accesses', fn($q) => $q->where('user_id', auth()->id()));
            }
        });
        // ao criar um workspace, gerar um uuid
        static::creating(fn($workspace) => $workspace->uuid = $workspace->uuid ?: (string) Str::uuid());
    }

    // Relationships

    public function accesses()
    {
        return $this->hasMany(Access::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    // Scopes

    public function scopeAllAccess($query)
    {
        return $query->withoutGlobalScope('member');
    }
}
