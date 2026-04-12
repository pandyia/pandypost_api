<?php

namespace App\Models;

use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit as BaseAudit;

class Audit extends BaseAudit
{
    use BelongsToWorkspace;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected static function booted(): void
    {
        static::creating(function (self $audit) {
            if (empty($audit->uuid)) {
                $audit->uuid = (string) Str::uuid();
            }
        });
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
