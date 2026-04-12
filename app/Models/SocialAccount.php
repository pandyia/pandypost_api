<?php

namespace App\Models;

use App\Enums\Platform;
use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class SocialAccount extends Model implements Auditable
{
    use AuditableTrait, BelongsToWorkspace;

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
        return $this->nickname . ' (' . $this->platform . ')';
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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(fn($account) => $account->uuid = $account->uuid ?: (string) Str::uuid());
    }

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

    // Business Methods

    public function isTokenExpired(): bool
    {
        if (!$this->expires_at) {
            return true;
        }

        return $this->expires_at->subMinutes(self::TOKEN_EXPIRY_MARGIN_MINUTES)->isPast();
    }

    public function getValidToken(): string
    {
        if (!$this->isTokenExpired()) {
            return $this->access_token;
        }

        if ($this->platform === Platform::YOUTUBE->value) {
            return $this->refreshYouTubeToken();
        }

        return $this->access_token;
    }

    public function revokeToken(): void
    {
        if ($this->access_token) {
            Http::post('https://oauth2.googleapis.com/revoke', [
                'token' => $this->access_token,
            ]);
        }
    }

    private function refreshYouTubeToken(): string
    {
        if (!$this->refresh_token) {
            throw new \Exception("Refresh token ausente. O usuário precisa reconectar a conta.");
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh_token,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        if ($response->successful()) {
            $data = $response->json();

            $this->update([
                'access_token' => $data['access_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);

            return $data['access_token'];
        }

        throw new \Exception("Não foi possível renovar o token do Google: " . $response->body());
    }
}