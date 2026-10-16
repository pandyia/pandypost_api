<?php

namespace App\Services;

use App\Enums\LandingBrand;
use App\Models\AppSetting;

class LandingSettingsService
{
    /**
     * Tema atual da landing. Sem valor salvo, ou com valor que não existe mais, devolve o padrão.
     */
    public function brand(): LandingBrand
    {
        $value = AppSetting::where('key', AppSetting::LANDING_BRAND)->value('value');

        return LandingBrand::tryFrom((string) $value) ?? LandingBrand::default();
    }

    public function updateBrand(LandingBrand $brand): LandingBrand
    {
        AppSetting::updateOrCreate(
            ['key' => AppSetting::LANDING_BRAND],
            ['value' => $brand->value],
        );

        return $brand;
    }
}
