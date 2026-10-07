<?php

namespace App\Models;

use App\Enums\Platform;
use App\Exceptions\SocialAccountException;
use App\Models\Traits\BelongsToWorkspace;
use App\Services\OAuthProviders\GoogleOAuthProvider;
use App\Services\OAuthProviders\InstagramOAuthProvider;
use App\Services\OAuthProviders\TikTokOAuthProvider;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\Response;
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

    // Validade usada quando a plataforma não informa expires_in.
    private const DEFAULT_TOKEN_TTL_SECONDS = [
        'youtube' => 3600,
        'tiktok' => 86400,
        'instagram' => 5184000, // 60 dias
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

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }

    // Business Methods

    // Considera vencido um pouco antes do prazo, para o token não expirar no meio de uma chamada.
    public function isTokenExpired(): bool
    {
        if (! $this->expires_at) {
            return true;
        }

        return $this->expires_at->subMinutes(self::TOKEN_EXPIRY_MARGIN_MINUTES)->isPast();
    }

    // Devolve o token atual ou renova se estiver vencido.
    public function getValidToken(): string
    {
        return $this->isTokenExpired() ? $this->refreshToken() : $this->access_token;
    }

    // Renova o token na plataforma e salva o novo; lança erro se a plataforma recusar.
    public function refreshToken(): string
    {
        return match ($this->platform) {
            Platform::YOUTUBE->value => $this->refreshWithRefreshToken(Platform::YOUTUBE, GoogleOAuthProvider::TOKEN_URL, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
            ]),
            Platform::TIKTOK->value => $this->refreshWithRefreshToken(Platform::TIKTOK, TikTokOAuthProvider::TOKEN_URL, [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
            ]),
            // O Instagram não usa refresh token: o próprio token de longa duração é trocado por um novo.
            Platform::INSTAGRAM->value => $this->saveRefreshedToken(Platform::INSTAGRAM, Http::get(InstagramOAuthProvider::REFRESH_URL, [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $this->access_token,
            ])),
            default => $this->access_token,
        };
    }

    // Revoga o acesso na plataforma ao desconectar a conta.
    public function revokeToken(): void
    {
        if (! $this->access_token) {
            return;
        }

        match ($this->platform) {
            Platform::YOUTUBE->value => Http::post(GoogleOAuthProvider::REVOKE_URL, [
                'token' => $this->access_token,
            ]),
            Platform::TIKTOK->value => Http::asForm()->post(TikTokOAuthProvider::REVOKE_URL, [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'token' => $this->access_token,
            ]),
            // O Instagram não tem revogação pela API: a desconexão só apaga o registro.
            default => null,
        };
    }

    private function refreshWithRefreshToken(Platform $platform, string $tokenUrl, array $credentials): string
    {
        if (! $this->refresh_token) {
            throw SocialAccountException::tokenRefreshFailed($platform->label());
        }

        return $this->saveRefreshedToken($platform, Http::asForm()->post($tokenUrl, [
            ...$credentials,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh_token,
        ]));
    }

    private function saveRefreshedToken(Platform $platform, Response $response): string
    {
        $accessToken = $response->json('access_token');

        if ($response->failed() || ! $accessToken) {
            Log::warning("Falha ao renovar o token do {$platform->label()} da conta {$this->id}.", [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            throw SocialAccountException::tokenRefreshFailed($platform->label());
        }

        $this->update([
            'access_token' => $accessToken,
            // O TikTok rotaciona o refresh token; Google e Instagram não mandam um novo.
            'refresh_token' => $response->json('refresh_token') ?? $this->refresh_token,
            'expires_at' => now()->addSeconds($response->json('expires_in') ?? self::DEFAULT_TOKEN_TTL_SECONDS[$platform->value]),
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
