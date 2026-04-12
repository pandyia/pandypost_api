<?php

namespace App\Services\OAuthProviders;

use App\Contracts\OAuthProviderInterface;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class DefaultSocialiteProvider
{
    //  implements OAuthProviderInterface
    // public function __construct(protected string $platform)
    // {
    // }

    // public function getRedirectUrl(?User $user = null): RedirectResponse
    // {
    //     $driver = Socialite::driver($this->platform)->stateless();
    //     if ($user) {
    //         $driver->with(['state' => encrypt($user->id)]);
    //     }
    //     return $driver->redirect();
    // }

    // public function syncAccount(User $user, ?string $code = null): SocialAccount
    // {
    //     $socialiteUser = Socialite::driver($this->platform)->stateless()->user();

    //     $existingAccount = SocialAccount::where('user_id', $user->id)
    //         ->where('platform', $this->platform)
    //         ->first();

    //     $data = [
    //         'user_id' => $user->id,
    //         'platform' => $this->platform,
    //         'platform_id' => $socialiteUser->getId(),
    //         'access_token' => $socialiteUser->token,
    //         'refresh_token' => $socialiteUser->refreshToken ?? $existingAccount?->refresh_token,
    //         'expires_at' => now()->addSeconds($socialiteUser->expiresIn ?? 3600),
    //         'nickname' => $socialiteUser->getName(),
    //         'avatar' => $socialiteUser->getAvatar(),
    //     ];

    //     return SocialAccount::updateOrCreate(
    //         ['user_id' => $user->id, 'platform' => $this->platform],
    //         $data
    //     );
    // }
}
