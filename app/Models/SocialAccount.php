<?php

namespace App\Models;

use App\Enums\Platform;
use App\Models\Traits\BelongsToWorkspace;
use App\Models\Traits\HasUuid;
use App\Services\Factories\SocialMediaFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A conta pertence ao workspace. `user_id` é só quem conectou/reconectou por último (auditoria e aviso de
 * desconexão); não participa de autorização (spec 017).
 */
class SocialAccount extends Model implements Auditable
{
    use AuditableTrait, BelongsToWorkspace, HasUuid;

    protected array $auditExclude = [
        'access_token',
        'refresh_token',
        'token_secret',
    ];

    public function generateTags(): array
    {
        return ['social-account'];
    }

    public function getAuditRepresentation(): string
    {
        return $this->nickname.' ('.$this->platform.')';
    }

    protected $fillable = [
        'uuid',
        'user_id',
        'workspace_id',
        'platform',
        'platform_id',
        'access_token',
        'refresh_token',
        'token_secret',
        'expires_at',
        'nickname',
        'username',
        'scopes',
        'avatar',
    ];

    protected $hidden = [
        'id',
        'access_token',
        'refresh_token',
        'token_secret',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    private const TOKEN_EXPIRY_MARGIN_MINUTES = 5;

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(SocialAccountMetricSnapshot::class);
    }

    // Business Methods

    public function isTokenExpired(): bool
    {
        if (! $this->expires_at) {
            return true;
        }

        return $this->expires_at->subMinutes(self::TOKEN_EXPIRY_MARGIN_MINUTES)->isPast();
    }

    private function hasScope(string $scope): bool
    {
        return in_array($scope, array_map('trim', explode(',', (string) $this->scopes)), true);
    }

    /** A conta tem o escopo que a rede exige para publicar (rede sem exigência: sempre pode). */
    public function canPublish(): bool
    {
        $required = Platform::tryFrom($this->platform)?->publishScope();

        return $required === null || $this->hasScope($required);
    }

    public function getValidToken(): string
    {
        $platform = Platform::tryFrom($this->platform);

        if (! $platform || ! $this->isTokenExpired()) {
            return $this->access_token;
        }

        $tokens = app(SocialMediaFactory::class)->tokenApi($platform)->refreshAccountToken($this);

        $this->update([
            'access_token' => $tokens['access_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
        ] + Arr::only($tokens, 'refresh_token'));

        return $tokens['access_token'];
    }

    public function revokeToken(): void
    {
        $platform = Platform::tryFrom($this->platform);

        if (! $this->access_token || ! $platform) {
            return;
        }

        app(SocialMediaFactory::class)->tokenApi($platform)->revokeAccountToken($this);
    }

    /**
     * Retorna a data da atividade mais recente (publicada ou agendada)
     */
    public function getLatestActivityDate(): ?Carbon
    {
        if ($this->relationLoaded('scheduledPosts')) {
            return $this->scheduledPosts
                ->whereNotNull('scheduled_at')
                ->sortByDesc('scheduled_at')
                ->first()?->scheduled_at;
        }

        $latestPost = $this->scheduledPosts()
            ->whereNotNull('scheduled_at')
            ->orderByDesc('scheduled_at')
            ->first();

        return $latestPost?->scheduled_at;
    }

    /**
     * Calcula quantos dias se passaram desde a última postagem ou agendamento
     */
    public function daysSinceLastActivity(): ?int
    {
        $latest = $this->getLatestActivityDate();

        if (! $latest) {
            return null; // Nunca postou
        }

        // Se tem algo agendado pro futuro, não está parado (retorna 0 ou negativo, mas limitamos a 0)
        if ($latest->isFuture()) {
            return 0;
        }

        return (int) $latest->diffInDays(now());
    }

    /**
     * Verifica se a conta está "parada" (sem posts há mais de X dias)
     * Default: 2 dias
     */
    public function isStale(int $daysThreshold = 2): bool
    {
        $days = $this->daysSinceLastActivity();

        if ($days === null) {
            return true; // Conta nova sem nenhum post é considerada "parada" (precisa de ação)
        }

        return $days >= $daysThreshold;
    }
}
