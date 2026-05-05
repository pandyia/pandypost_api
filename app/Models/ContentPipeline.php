<?php

namespace App\Models;

use App\Enums\PipelineStage;
use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ContentPipeline extends Model implements Auditable
{
    use AuditableTrait, BelongsToWorkspace, SoftDeletes;

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
        'stage'    => PipelineStage::class,
        'due_date' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(fn($card) => $card->uuid = $card->uuid ?: (string) Str::uuid());
    }

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

    // Business Methods

    public function isScheduled(): bool
    {
        return $this->stage === PipelineStage::SCHEDULED;
    }
}
