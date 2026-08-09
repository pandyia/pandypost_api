<?php

namespace App\Models;

use App\Models\Traits\BelongsToWorkspace;
use App\Observers\UserObserver;
use DB;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements Auditable
{
    use HasFactory, Notifiable, HasApiTokens, BelongsToWorkspace, AuditableTrait;

    protected array $auditExclude = [
        'password',
        'remember_token',
    ];

    /**
     * {@inheritdoc}
     *
     * Rotas públicas (login, register) alteram o User sem um guard ativo.
     * Como o model auditado É o próprio User, preenchemos user_id e
     * workspace_id a partir de $this, seguindo o design pattern do pacote.
     */
    public function transformAudit(array $data): array
    {
        if (empty($data['user_id'])) {
            $data['user_type'] = self::class;
            $data['user_id'] = $this->getKey();
        }

        if (empty($data['workspace_id']) && $this->access_id) {
            $data['workspace_id'] = $this->currentAccess?->workspace_id;
        }

        return $data;
    }

    public function generateTags(): array
    {
        return match ($this->auditEvent) {
            'created' => ['authentication', 'registration'],
            'updated' => ['authentication', 'account'],
            'deleted' => ['authentication', 'deletion'],
            default   => ['authentication'],
        };
    }

    public function getAuditRepresentation(): string
    {
        return $this->name . ' <' . $this->email . '>';
    }

    // Constants

    public const TOKEN_EXPIRATION_HOURS = 2;
    private const TOKEN_DEVICE_NAME = 'web';

    protected string $workspaceRelation = 'accesses';

    // Properties

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'is_active',
        'remember_me',
        'is_super_admin',
        'access_id',
    ];

    protected $hidden = [
        'id',
        'password',
        'remember_token',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(fn($user) => $user->uuid = $user->uuid ?: (string)  Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    // Relationships

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(Access::class);
    }

    // Exibe o workspace atual através do access_id
    public function currentAccess(): BelongsTo
    {
        return $this->belongsTo(Access::class, 'access_id');
    }

    public function verificationEmailToken(): HasOne
    {
        return $this->hasOne(VerificationEmailToken::class);
    }

    // Business Methods

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Gate de publicação por assinatura.
     *
     * FASE 2: liberado (limites deferidos; assinatura passa a vir do checkout na
     * fase Cliente). Será reativado via workspace->subscription('default')->valid()
     * quando o checkout existir (ver App\Http\Middleware\EnsureSubscriptionAccess).
     */
    public function hasValidSubscriptionForPublishing(): bool
    {
        return true;
    }

    public function resolveCurrentAccess(): ?Access
    {
        $this->unsetRelation('currentAccess');
        $access = $this->currentAccess;

        if (!$access) {
            $access = $this->accesses()
                ->whereHas('workspace', fn($q) => $q->where('is_personal_team', true))
                ->first();
            $this->update(['access_id' => $access?->id]);
        }

        return $access;
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->accesses()
            ->whereHas('role.permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    public function loginExpiration(bool $rememberMe)
    {
        return DB::transaction(function () use ($rememberMe) {
            $this->update(['remember_me' => $rememberMe]);
            return match ($rememberMe) {
                true => now()->addDays(intval(config('auth.token_expiration_days'))),
                false => now()->addMinutes(intval(config('sanctum.expiration')))
            };
        });
    }

    public function generateAccessToken(?\DateTimeInterface $expiration = null): string
    {
        return $this->createToken(
            self::TOKEN_DEVICE_NAME,
            ['*'],
            $expiration
        )->plainTextToken;
    }

    public static function findByEmail(string $email): ?self
    {
        return self::where('email', $email)->first();
    }

    public function accessWithWorkspace($expiration = null): array
    {
        $access = $this->resolveCurrentAccess();

        return [
            'user' => $this,
            'token' => $this->generateAccessToken($expiration),
            'current_workspace' => $access?->workspace,
        ];
    }

    public function hasAccessToWorkspace(string $uuid): bool
    {
        return $this->accesses()
            ->whereHas('workspace', fn($q) => $q->where('uuid', $uuid))
            ->exists();
    }
}
