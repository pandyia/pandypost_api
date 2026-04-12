<?php

namespace App\Services;

use App\Exceptions\SocialAccountException;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Factories\OAuthProviderFactory;
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

    public function syncAccount(User|Workspace $context, string $platform, ?string $code = null): SocialAccount
    {
        $provider = $this->factory->make($platform);
        return $provider->syncAccount($context, $code);
    }

    public function disconnect(string $uuid): void
    {
        $account = $this->findByUuid($uuid);

        DB::transaction(function () use ($account) {
            ScheduledPost::where('social_account_id', $account->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $account->revokeToken();
            $account->delete();
        });
    }
}
