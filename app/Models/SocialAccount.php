<?php

namespace App\Models;

use App\Enums\Platform;
use App\Exceptions\SocialAccountException;
use App\Models\Traits\BelongsToWorkspace;
use App\Services\OAuthProviders\TikTokOAuthProvider;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        static::creating(fn ($account) => $account->uuid = $account->uuid ?: (string) Str::uuid());
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
        if (! $this->expires_at) {
            return true;
        }

        return $this->expires_at->subMinutes(self::TOKEN_EXPIRY_MARGIN_MINUTES)->isPast();
    }

    public function getValidToken(): string
    {
        if (! $this->isTokenExpired()) {
            return $this->access_token;
        }

        return match ($this->platform) {
            Platform::YOUTUBE->value => $this->refreshYouTubeToken(),
            Platform::INSTAGRAM->value => $this->refreshInstagramToken(),
            Platform::TIKTOK->value => $this->refreshTikTokToken(),
            default => $this->access_token,
        };
    }

    public function revokeToken(): void
    {
        if (! $this->access_token) {
            return;
        }

        match ($this->platform) {
            Platform::YOUTUBE->value => Http::post('https://oauth2.googleapis.com/revoke', [
                'token' => $this->access_token,
            ]),
            Platform::TIKTOK->value => Http::asForm()->post(TikTokOAuthProvider::REVOKE_URL, [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'token' => $this->access_token,
            ]),
            // Instagram não tem endpoint público de revogação via API.
            // A desconexão é feita apenas no lado do app (delete do registro).
            default => null,
        };
    }

    private function refreshYouTubeToken(): string
    {
        if (! $this->refresh_token) {
            throw new \Exception('Refresh token ausente. O usuário precisa reconectar a conta.');
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

        throw new \Exception('Não foi possível renovar o token do Google: '.$response->body());
    }

    private function refreshInstagramToken(): string
    {
        $response = Http::get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $this->access_token,
        ]);

        if ($response->successful()) {
            $data = $response->json();

            $this->update([
                'access_token' => $data['access_token'],
                'expires_at' => now()->addSeconds($data['expires_in'] ?? 5184000),
            ]);

            return $data['access_token'];
        }

        throw new \Exception('Não foi possível renovar o token do Instagram: '.$response->body());
    }

    private function refreshTikTokToken(): string
    {
        if (! $this->refresh_token) {
            throw SocialAccountException::tokenRefreshFailed(Platform::TIKTOK->label());
        }

        $response = Http::asForm()->post(TikTokOAuthProvider::TOKEN_URL, [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh_token,
        ]);

        $accessToken = $response->json('access_token');

        if (! $response->successful() || ! $accessToken) {
            Log::warning("Falha ao renovar o token do TikTok da conta {$this->id}.", [
                'status' => $response->status(),
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
            ]);
            throw SocialAccountException::tokenRefreshFailed(Platform::TIKTOK->label());
        }

        // O TikTok pode rotacionar o refresh token a cada renovação.
        $this->update([
            'access_token' => $accessToken,
            'refresh_token' => $response->json('refresh_token') ?? $this->refresh_token,
            'expires_at' => now()->addSeconds($response->json('expires_in') ?? 86400),
        ]);

        return $accessToken;
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
