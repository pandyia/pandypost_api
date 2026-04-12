<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreInviteRequest;
use App\Http\Resources\InviteResource;
use App\Http\Resources\ReceivedInviteResource;
use App\Models\Invite;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InviteController extends BaseController
{
    protected ?string $resourceClass = InviteResource::class;

    protected static string $permissionGroup = 'invites';
    protected static array $permissionMethods = [
        'view' => ['index'],
        'create' => ['send'],
        'delete' => ['destroy'],
    ];

    public function __construct(
        private InviteService $inviteService,
    ) {
        parent::__construct($inviteService);
    }

    public function send(StoreInviteRequest $request): JsonResponse
    {
        $this->inviteService->sendInvite($request->validated());

        return response()->json(['message' => 'Convite enviado com sucesso'], 200);
    }

    public function received(Request $request): Response
    {
        $data = $this->inviteService->receivedInvites($request->input());

        return ReceivedInviteResource::collection($data)->response();
    }

    public function accept(Invite $invite): JsonResponse
    {
        $this->inviteService->accept($invite);

        return response()->json(['message' => 'Convite aceito com sucesso']);
    }

    public function decline(Invite $invite): JsonResponse
    {
        $this->inviteService->decline($invite);

        return response()->json(['message' => 'Convite rejeitado com sucesso']);
    }
}
