<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use App\Models\Traits\BelongsToWorkspace;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ScheduledPost extends Model implements Auditable
{
    use AuditableTrait, BelongsToWorkspace;

    // Sem coluna workspace_id: o escopo de tenant vem da conta social do post.
    protected string $workspaceRelation = 'socialAccount';

    public function generateTags(): array
    {
        return ['post'];
    }

    /** Dias que a mídia de um post com falha fica guardada para reenvio. */
    public const MEDIA_RETENTION_DAYS = 5;

    protected $fillable = [
        'uuid',
        'user_id',
        'social_account_id',
        'platform',
        'type',
        'media_path',
        'title',
        'caption',
        'scheduled_at',
        'status',
        'published_at',
        'error_message',
        'payload',
        'container_id',
        'container_created_at',
        'platform_post_id',
        'platform_post_url',
        'failed_at',
        'media_purged_at',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'platform' => Platform::class,
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'container_created_at' => 'datetime',
        'failed_at' => 'datetime',
        'media_purged_at' => 'datetime',
        'payload' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(fn ($post) => $post->uuid = $post->uuid ?: (string) Str::uuid());

        // A falha pode vir de vários caminhos (jobs, serviços); o prazo de retenção da mídia conta daqui.
        static::updating(function (self $post) {
            if ($post->isDirty('status') && $post->status === ScheduledPostStatus::FAILED->value) {
                $post->failed_at = now();
            }
        });

        // Ponto central de sucesso: todas as redes marcam o post como publicado via update().
        static::updated(function (self $post) {
            if ($post->wasChanged('status') && $post->status === ScheduledPostStatus::PUBLISHED->value) {
                app(NotificationService::class)->notifyPostPublished($post);
            }

            // Ponto central de falha: avisa em qualquer caminho (PublishPostJob, Instagram, TikTok).
            if ($post->wasChanged('status') && $post->status === ScheduledPostStatus::FAILED->value) {
                app(NotificationService::class)->notifyPostFailed($post);
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    // Business Methods

    public function hasValidContainer(): bool
    {
        if (! $this->container_id || ! $this->container_created_at) {
            return false;
        }

        if ($this->status === ScheduledPostStatus::FAILED->value) {
            return false;
        }

        // Containers do Instagram expiram após 24h
        return $this->container_created_at->diffInHours(now()) < 24;
    }

    /** Falhou e a mídia ainda está guardada. */
    public function canRetry(): bool
    {
        return $this->status === ScheduledPostStatus::FAILED->value && $this->media_purged_at === null;
    }

    /** Até quando a mídia fica guardada para reenvio. */
    public function retryDeadline(): ?Carbon
    {
        return $this->canRetry() ? $this->failed_at?->copy()->addDays(self::MEDIA_RETENTION_DAYS) : null;
    }

    public function totalPostsForAccount(): int
    {
        return $this->socialAccount?->scheduledPosts()->count() ?? 0;
    }

    public function failedPostsForAccount(): int
    {
        return $this->socialAccount?->scheduledPosts()->where('status', ScheduledPostStatus::FAILED->value)->count() ?? 0;
    }

    public function getPlatformPostUrl(): ?string
    {
        return match ($this->platform) {
            // O id que a Graph API devolve não é o código da URL: o link vem do permalink gravado na publicação.
            Platform::INSTAGRAM => $this->platform_post_url,
            Platform::YOUTUBE => $this->platform_post_id ? "https://www.youtube.com/watch?v={$this->platform_post_id}" : null,
            _ => null,
        };
    }
}
