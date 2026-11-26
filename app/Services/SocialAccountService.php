<?php

namespace App\Services;

use App\Exceptions\SocialAccountException;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Factories\OAuthProviderFactory;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\DB;

class SocialAccountService extends BaseService
{
    protected array $normalFilter = ['platform'];

    protected array $with = [];

    public function __construct(
        SocialAccount $socialAccount,
        protected OAuthProviderFactory $factory
    ) {
        parent::__construct($socialAccount);
    }

    public function getRedirectUrl(string $platform, ?User $user = null): string
    {
        $provider = $this->factory->make($platform);

        return $provider->getRedirectUrl($user);
    }

    /** Falha com `platform_not_supported` se a plataforma não existe (o callback confere antes de gastar o state). */
    public function ensurePlatformSupported(string $platform): void
    {
        $this->factory->make($platform);
    }

    public function syncAccount(User $user, string $platform, ?string $code = null, ?Workspace $workspace = null): SocialAccount
    {
        $provider = $this->factory->make($platform);

        return $provider->syncAccount($user, $code, $workspace);
    }

    /** Revalida o contexto do state já consumido (workspace, iniciador e permissão) e conecta a conta. */
    public function connectFromCallback(string $platform, ?string $code, array $state): void
    {
        $workspace = Workspace::where('uuid', $state['workspace_uuid'])->first();
        $initiator = User::find($state['user_id']);

        // O iniciador precisa continuar com acesso ao workspace (pode ter saído entre o início e o callback).
        if (! $workspace || ! $initiator
            || ! $initiator->accesses()->where('workspace_id', $workspace->id)->exists()) {
            throw SocialAccountException::oauthStateContextInvalid();
        }

        // O papel do iniciador pode ter mudado nos 10 minutos do state: precisa ainda poder conectar contas aqui.
        if (! $initiator->isSuperAdmin() && ! $initiator->hasPermissionInWorkspace('social_accounts.connect', $workspace->id)) {
            throw SocialAccountException::connectPermissionDenied();
        }

        $this->syncAccount($initiator, $platform, $code, $workspace);
    }

    public function disconnect(string $uuid): void
    {
        $account = $this->findByUuid($uuid);

        $cancelled = DB::transaction(function () use ($account) {
            $posts = ScheduledPost::pending()
                ->where('social_account_id', $account->id)
                ->get();

            ScheduledPost::whereKey($posts->modelKeys())->update(['status' => 'cancelled']);

            $account->revokeToken();
            $account->delete();

            return $posts;
        });

        // Fora da transação (apagar no S3 não tem rollback) e só o que nenhum outro post ainda usa.
        $storage = app(StorageService::class);
        foreach ($cancelled as $post) {
            $storage->deleteIfUnused($post->mediaPaths(), $post->id);
        }
    }
}
