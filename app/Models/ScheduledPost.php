<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\ScheduledPostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ScheduledPost extends Model implements Auditable
{
    use AuditableTrait;

    public function generateTags(): array
    {
        return ['post'];
    }

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
    ];

    protected $hidden = ['id'];

    private const ERROR_MESSAGE_MAX_LENGTH = 255;

    protected $casts = [
        'platform' => Platform::class,
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'container_created_at' => 'datetime',
        'payload' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(fn ($post) => $post->uuid = $post->uuid ?: (string) Str::uuid());
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

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('scheduled_at', '<=', now());
    }

    // Business Methods

    public function markAsPublished(?string $platformPostId): void
    {
        $this->update([
            'status' => ScheduledPostStatus::PUBLISHED->value,
            'platform_post_id' => $platformPostId,
            'published_at' => now(),
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => ScheduledPostStatus::FAILED->value,
            'error_message' => mb_substr($message, 0, self::ERROR_MESSAGE_MAX_LENGTH),
        ]);
    }

    // Arquivos do post no S3: a mídia e, se houver, a thumbnail.
    public function mediaPaths(): array
    {
        return array_values(array_filter([$this->media_path, $this->payload['thumbnail_path'] ?? null]));
    }

    // Diz se o container do warmup ainda pode ser usado na publicação.
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
            // O link do Instagram usa o shortcode do post, guardado como permalink na publicação.
            Platform::INSTAGRAM => $this->payload['permalink'] ?? null,
            Platform::YOUTUBE => $this->platform_post_id ? "https://www.youtube.com/watch?v={$this->platform_post_id}" : null,
            // O TikTok resolve o vídeo pelo id; o @usuário pode ficar vazio.
            Platform::TIKTOK => $this->platform_post_id ? "https://www.tiktok.com/@/video/{$this->platform_post_id}" : null,
            default => null,
        };
    }
}
