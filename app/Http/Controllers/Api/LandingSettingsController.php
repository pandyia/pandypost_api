<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LandingSettingsResource;
use App\Services\LandingSettingsService;

/**
 * @group Landing (público)
 *
 * Configuração visual da landing page, definida pelo super admin.
 */
class LandingSettingsController extends Controller
{
    public function __construct(
        private readonly LandingSettingsService $service,
    ) {}

    /**
     * Tema da landing
     *
     * Tema que a landing deve usar. Não precisa de login. Sem configuração salva, devolve `pop`.
     *
     * Limite: 45 requisições por minuto.
     *
     * @unauthenticated
     *
     * @response 200 {"data": {"brand": "pop"}}
     */
    public function show(): LandingSettingsResource
    {
        return new LandingSettingsResource($this->service->brand());
    }
}
