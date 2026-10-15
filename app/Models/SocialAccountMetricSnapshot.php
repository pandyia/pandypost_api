<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot diário das métricas de uma conta social (1 linha por conta por dia).
 * Escopo de tenant vem da conta: só é lido a partir de um SocialAccount já escopado.
 */
class SocialAccountMetricSnapshot extends Model
{
    use MassPrunable;

    // Cobre a comparação "ano contra ano anterior" com folga.
    public const RETENTION_DAYS = 400;

    protected $fillable = [
        'social_account_id',
        'date',
        'followers',
        'following',
        'media_count',
        'total_likes',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'date' => 'date',
        'followers' => 'integer',
        'following' => 'integer',
        'media_count' => 'integer',
        'total_likes' => 'integer',
    ];

    /**
     * Grava sempre 'Y-m-d': mantém a comparação de datas e o índice único
     * iguais no PostgreSQL (coluna date) e no SQLite (texto) dos testes.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    // Scopes

    /**
     * Snapshot mais recente até a data (inclusive).
     */
    public function scopeOnOrBefore($query, string $date)
    {
        return $query->where('date', '<=', $date)->orderByDesc('date');
    }

    public function prunable(): Builder
    {
        return static::where('date', '<', now()->subDays(self::RETENTION_DAYS)->toDateString());
    }
}
