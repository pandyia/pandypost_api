<?php

namespace App\Http\Controllers\Api;

use App\Enums\Platform;
use App\Http\Resources\TikTokCreatorInfoResource;
use App\Models\SocialAccount;
use App\Services\TikTokService;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Contas sociais
 */
class TikTokCreatorInfoController extends BaseController
{
    protected static string $permissionGroup = 'social_accounts';

    protected static array $permissionMethods = [
        'view' => ['__invoke'],
    ];

    public function __construct(
        private TikTokService $tikTokService
    ) {
        parent::__construct($tikTokService);
    }

    /**
     * Opções de publicação do TikTok
     *
     * Consulta o TikTok na hora e devolve o que a conta pode usar ao postar. Chame ao abrir o formulário de post do TikTok:
     * monte o seletor de `privacy_level` só com `privacy_level_options` e desabilite os campos `disable_comment`,
     * `disable_duet` e `disable_stitch` quando o respectivo `*_disabled` for `true`. Usar uma privacidade fora da lista
     * faz o post falhar (`tiktok_privacy_not_allowed`).
     *
     * **Permissão:** `social_accounts.view`
     *
     * @urlParam socialAccount_uuid string required UUID da conta social do TikTok. Example: 5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f
     *
     * @response 200 {"data": {"creator_nickname": "Ana Souza", "privacy_level_options": ["PUBLIC_TO_EVERYONE", "MUTUAL_FOLLOW_FRIENDS", "SELF_ONLY"], "comment_disabled": false, "duet_disabled": false, "stitch_disabled": true, "max_video_post_duration_sec": 600}}
     *
     * @responseField data.creator_nickname string|null Apelido do criador no TikTok.
     * @responseField data.privacy_level_options string[] Valores aceitos em `privacy_level`. Possíveis: PUBLIC_TO_EVERYONE, MUTUAL_FOLLOW_FRIENDS, FOLLOWER_OF_CREATOR, SELF_ONLY. Pode vir vazio.
     * @responseField data.comment_disabled boolean `true` = a conta desativou comentários; não permita ativá-los.
     * @responseField data.duet_disabled boolean `true` = a conta desativou duetos.
     * @responseField data.stitch_disabled boolean `true` = a conta desativou stitch.
     * @responseField data.max_video_post_duration_sec integer|null Duração máxima do vídeo, em segundos.
     *
     * @response 404 scenario="conta não existe neste workspace ou não é do TikTok" {"message": "Not Found"}
     * @response 409 scenario="conta sem permissão de publicar ou token revogado" {"error": "tiktok_scope_missing", "message": "A conta do TikTok não tem permissão para publicar. Desconecte e reconecte a conta TikTok."}
     * @response 409 scenario="token inválido" {"error": "access_token_invalid", "message": "O acesso à conta do TikTok expirou ou foi revogado. Reconecte a conta TikTok."}
     * @response 502 scenario="TikTok fora do ar ou resposta inesperada" {"error": "tiktok_api_unavailable", "message": "Não foi possível se comunicar com o TikTok. Tente novamente em instantes."}
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "social_accounts.view"}
     */
    public function __invoke(SocialAccount $socialAccount): Response
    {
        abort_unless($socialAccount->platform === Platform::TIKTOK->value, 404);

        return (new TikTokCreatorInfoResource($this->tikTokService->creatorInfo($socialAccount)))->response();
    }
}
