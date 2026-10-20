<?php

namespace App\Models;

use App\Enums\PipelineStage;
use App\Models\Traits\BelongsToWorkspace;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ContentPipeline extends Model implements Auditable
{
    use AuditableTrait, BelongsToWorkspace, HasUuid, SoftDeletes;

    public function generateTags(): array
    {
        return ['pipeline'];
    }

    protected $fillable = [
        'uuid',
        'workspace_id',
        'user_id',
        'scheduled_post_id',
        'title',
        'description',
        'platform',
        'stage',
        'due_date',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'stage' => PipelineStage::class,
        'due_date' => 'date',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class);
    }
}
