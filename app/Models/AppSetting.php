<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuração global do SaaS, em formato chave/valor (ex.: `landing.brand`).
 * Não é recurso exposto: não tem uuid e só é lido/gravado por services.
 */
class AppSetting extends Model
{
    public const LANDING_BRAND = 'landing.brand';

    protected $fillable = ['key', 'value'];
}
