<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\SocialAccountResource;
use App\Models\Workspace;
use App\Services\SocialAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialAccountController extends BaseController
{
    protected ?string $resourceClass = SocialAccountResource::class;

    protected static string $permissionGroup = 'social_accounts';
    protected static array $permissionMethods = [
        'view'       => ['index'],
        'connect'    => ['redirect'],
        'disconnect' => ['destroy'],
    ];

    public function __construct(
        private SocialAccountService $socialAccountService
    ) {
        parent::__construct($socialAccountService);
    }

    /**
     * GET /api/social-accounts/{platform}/auth
     * Retorna JSON com a URL de OAuth (não faz redirect direto, evita CORS).
     */
    public function redirect(Request $request, string $platform): JsonResponse
    {
        $url = $this->socialAccountService->getRedirectUrl($platform, $request->user());

        return response()->json(['url' => $url]);
    }

    /**
     * GET /api/social-accounts/{platform}/callback (público — state contém workspace_uuid)
     * Redireciona popup para o frontend callback com ?success ou ?error
     */
    public function callback(string $platform, Request $request)
    {
        $frontendCallbackUrl = config('app.frontend_url') . '/social-accounts/callback';

        $state = $request->input('state');

        if (!$state) {
            return redirect($frontendCallbackUrl . '?error=' . urlencode('Estado OAuth inválido.'));
        }

        try {
            $workspaceUuid = decrypt($state);
        } catch (\Exception $e) {
            return redirect($frontendCallbackUrl . '?error=' . urlencode('Estado OAuth inválido.'));
        }

        $workspace = Workspace::where('uuid', $workspaceUuid)->first();

        if (!$workspace) {
            return redirect($frontendCallbackUrl . '?error=' . urlencode('Workspace não encontrado.'));
        }

        try {
            $this->socialAccountService->syncAccount($workspace, $platform, $request->input('code'));
            return redirect($frontendCallbackUrl . '?success=1');
        } catch (\Exception $e) {
            return redirect($frontendCallbackUrl . '?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * DELETE /api/social-accounts/{socialAccount}
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->socialAccountService->disconnect($id);

        return response()->json([
            'message' => 'Conta social desconectada com sucesso.',
        ]);
    }

    /**
     * GET /api/social-accounts/schedules
     */
    public function schedules(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->socialAccountService->paginate(
            $request->input(),
            with: ['scheduledPosts'],
        );

        return SocialAccountResource::collection($data)->response();
    }
}

