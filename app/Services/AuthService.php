<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Exceptions\TokenException;
use App\Exceptions\LoginException;

use App\Models\ResetPassword;
use App\Models\User;
use App\Models\VerificationEmailToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\NotificationService;

class AuthService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    public function signup(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),

            ]);

            $token = VerificationEmailToken::generate($user);

            $this->notificationService->send(
                $user,
                NotificationType::EMAIL_VERIFICATION,
                ['token' => $token]
            );

            return [
                'user' => $user,
                'token' => $user->generateAccessToken(),
                'status' => 'unverified',
            ];
        });
    }

    public function login(array $data): string|array
    {
        $user = User::findByEmail($data['email']);

        if (!$user) {
            throw LoginException::userNotFound();
        }

        if (!Hash::check($data['password'], $user->password)) {
            throw LoginException::invalidCredentials();
        }

        if (!$user->hasVerifiedEmail()) {
            throw LoginException::emailNotVerified();
        }

        $expiration = $user->loginExpiration($data['remember_me'] ?? false);
        return $user->accessWithWorkspace($expiration);
    }

    public function resendVerification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw TokenException::alreadyVerified();
        }

        $user->verificationEmailToken()->delete();

        $token = VerificationEmailToken::generate($user);

        $this->notificationService->send(
            $user,
            NotificationType::EMAIL_VERIFICATION,
            ['token' => $token]
        );
    }

    public function confirmEmail(string $token): void
    {
        $verificationToken = VerificationEmailToken::where('token', $token)->first();

        if (!$verificationToken) {
            throw TokenException::invalidToken();
        }

        if ($verificationToken->isExpired()) {
            throw TokenException::expiredToken();
        }

        $user = $verificationToken->user;

        if ($user->hasVerifiedEmail()) {
            throw TokenException::alreadyVerified();
        }
        $user->markEmailAsVerified();
        $verificationToken->delete();
    }

    public function forgotPassword(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return;
        }

        $token = ResetPassword::generate($user);

        $this->notificationService->send(
            $user,
            NotificationType::PASSWORD_RESET,
            ['token' => $token]
        );
    }

    public function resetPassword(array $data): void
    {
        $reset = ResetPassword::findByToken($data['token']);

        if (!$reset || $reset->email !== $data['email']) {
            throw TokenException::invalidToken();
        }

        if ($reset->isExpired()) {
            throw TokenException::expiredToken();
        }

        $user = $reset->user;

        $user->update([
            'password' => Hash::make($data['password']),
            'remember_token' => null,
        ]);

        $reset->delete();
        $user->tokens()->delete();
    }
}
