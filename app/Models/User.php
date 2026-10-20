<?php

namespace App\Models;

use App\Enums\NotificationType;
use App\Models\Traits\BelongsToWorkspace;
use App\Models\Traits\HasUuid;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements Auditable
{
    use AuditableTrait, BelongsToWorkspace, HasApiTokens, HasFactory, HasUuid, Notifiable;

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
            default => ['authentication'],
        };
    }

    public function getAuditRepresentation(): string
    {
        return $this->name.' <'.$this->email.'>';
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
        'phone',
        'bio',
        'timezone',
        'avatar_path',
    ];

    protected $hidden = [
        'id',
        'password',
        'remember_token',
        'avatar_path',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'remember_me' => 'boolean',
            'notification_preferences' => 'array',
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

    // Central de notificações (model próprio, com workspace_id)
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
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

        if (! $access) {
            $access = $this->accesses()
                ->whereHas('workspace', fn ($q) => $q->withoutGlobalScopes()->where('is_personal_team', true))
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

    /**
     * Permissão no workspace informado (e só nele), ao contrário de `hasPermission`, que vale
     * para qualquer workspace do usuário.
     */
    public function hasPermissionInWorkspace(string $permissionName, int $workspaceId): bool
    {
        return $this->accesses()
            ->where('workspace_id', $workspaceId)
            // Sem o escopo de workspace do Role: ele filtraria pelo workspace corrente, não pelo informado.
            ->whereHas('role', fn ($role) => $role->withoutGlobalScopes()
                ->whereHas('permissions', fn ($query) => $query->where('name', $permissionName)))
            ->exists();
    }

    /**
     * Preferências de e-mail por família (ausente = ligado). Billing é sempre e-mail.
     *
     * @return array<string, bool>
     */
    public function emailPreferences(): array
    {
        $saved = $this->notification_preferences ?? [];

        return collect(NotificationType::PREFERENCE_FAMILIES)
            ->mapWithKeys(fn (string $family) => [$family => (bool) ($saved[$family] ?? true)])
            ->all();
    }

    /**
     * Aviso no app (central + tempo real) por família. Ausente = ligado, exceto as de
     * `NotificationType::IN_APP_OFF_BY_DEFAULT` (opt-in). Billing é sempre ligado.
     *
     * @return array<string, bool>
     */
    public function inAppPreferences(): array
    {
        $saved = ($this->notification_preferences ?? [])['in_app'] ?? [];

        return collect(NotificationType::PREFERENCE_FAMILIES)
            ->mapWithKeys(fn (string $family) => [
                $family => (bool) ($saved[$family] ?? ! in_array($family, NotificationType::IN_APP_OFF_BY_DEFAULT, true)),
            ])
            ->all();
    }

    /**
     * Preferências expostas na API: e-mail por família (campos soltos, padrão ligado) e o aviso
     * no app por família em `in_app`.
     *
     * @return array<string, bool|array<string, bool>>
     */
    public function notificationSettings(): array
    {
        return $this->emailPreferences() + [
            'in_app' => $this->inAppPreferences(),
        ];
    }

    public function wantsInAppFor(NotificationType $type): bool
    {
        $family = $type->family();

        return match (true) {
            $family === null => true,
            $family === 'billing' => true,
            default => $this->inAppPreferences()[$family] ?? true,
        };
    }

    public function wantsEmailFor(NotificationType $type): bool
    {
        $family = $type->family();

        return match (true) {
            $family === null => true,
            $family === 'billing' => true,
            default => $this->emailPreferences()[$family] ?? true,
        };
    }

    public function loginExpiration()
    {
        return match ($this->remember_me) {
            true => now()->addDays(intval(config('auth.token_expiration_days'))),
            false => now()->addMinutes(intval(config('sanctum.expiration')))
        };
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
            ->whereHas('workspace', fn ($q) => $q->where('uuid', $uuid))
            ->exists();
    }
}
