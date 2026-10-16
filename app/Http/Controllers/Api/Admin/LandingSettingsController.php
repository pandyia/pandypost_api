<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LandingBrand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLandingSettingsRequest;
use App\Http\Resources\LandingSettingsResource;
use App\Services\LandingSettingsService;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin: landing
 *
 * Tema da landing page, **só para o super admin** (dono do SaaS). Para qualquer outro usuário a resposta é 403.
 */
class LandingSettingsController extends Controller
{
    public function __construct(
        private readonly LandingSettingsService $service,
    ) {}

    /**
     * Trocar o tema da landing
     *
     * `brand` aceita `pop`, `violeta`, `coral` ou `bambu`. Repetir o mesmo valor não muda nada.
     *
     * @bodyParam brand string required Tema da landing. Example: coral
     *
     * @response 200 {"message": "Tema da landing atualizado.", "data": {"brand": "coral"}}
     * @response 403 {"message": "Você não tem permissão para acessar este recurso."}
     * @response 422 {"message": "Esse tema não está disponível para a landing.", "errors": {"brand": ["Esse tema não está disponível para a landing."]}}
     */
    public function update(): JsonResponse
    {
        $data = app(UpdateLandingSettingsRequest::class)->validated();

        $brand = $this->service->updateBrand(LandingBrand::from($data['brand']));

        return response()->json([
            'message' => 'Tema da landing atualizado.',
            'data' => new LandingSettingsResource($brand),
        ]);
    }
}
