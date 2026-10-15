<?php

use App\Models\ResetPassword;
use App\Models\User;
use App\Models\VerificationEmailToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Notification::fake();
});

describe('confirmação de email', function () {

    it('retorna 400 invalid_token para token inexistente', function () {
        $this->postJson('/api/auth/confirm-email', ['token' => 'nao-existe'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_token');
    });

    it('retorna 400 expired_token para token expirado e não verifica o email', function () {
        $user = User::factory()->create(['email_verified_at' => null]);
        VerificationEmailToken::create([
            'user_id' => $user->id,
            'token' => 'token-expirado',
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/auth/confirm-email', ['token' => 'token-expirado'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'expired_token');

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('retorna 422 sem token', function () {
        $this->postJson('/api/auth/confirm-email', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    });

});

describe('redefinição de senha', function () {

    beforeEach(function () {
        $this->user = User::factory()->create(['email' => 'dono@example.com']);
        ResetPassword::create([
            'email' => 'dono@example.com',
            'token' => 'token-reset',
            'expires_at' => now()->addHour(),
        ]);
    });

    it('rejeita token válido usado com email de outra pessoa', function () {
        User::factory()->create(['email' => 'outro@example.com']);

        $this->postJson('/api/auth/password-reset', [
            'token' => 'token-reset',
            'email' => 'outro@example.com',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_token');
    });

    it('não permite reutilizar o token após redefinir', function () {
        $payload = [
            'token' => 'token-reset',
            'email' => 'dono@example.com',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ];

        $this->postJson('/api/auth/password-reset', $payload)->assertOk();
        $this->postJson('/api/auth/password-reset', $payload)
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_token');
    });

    it('revoga todos os tokens de acesso ao redefinir a senha', function () {
        $this->user->createToken('sessao-antiga');

        $this->postJson('/api/auth/password-reset', [
            'token' => 'token-reset',
            'email' => 'dono@example.com',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertOk();

        expect($this->user->tokens()->count())->toBe(0);
    });

    it('retorna 400 expired_token para token expirado', function () {
        ResetPassword::where('email', 'dono@example.com')->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/auth/password-reset', [
            'token' => 'token-reset',
            'email' => 'dono@example.com',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertStatus(400)->assertJsonPath('error', 'expired_token');
    });

    it('não revela se o email existe ao pedir recuperação', function () {
        $existing = $this->postJson('/api/auth/forgot-password', ['email' => 'dono@example.com']);
        $missing = $this->postJson('/api/auth/forgot-password', ['email' => 'ninguem@example.com']);

        expect($missing->status())->toBe($existing->status())
            ->and($missing->json('message'))->toBe($existing->json('message'));
    });

});

describe('logout', function () {

    it('revoga apenas o token usado na requisição', function () {
        $user = User::factory()->create();
        $current = $user->createToken('atual')->plainTextToken;
        $user->createToken('outro-dispositivo');

        $this->withToken($current)->postJson('/api/logout')->assertNoContent();

        expect($user->tokens()->pluck('name')->all())->toBe(['outro-dispositivo']);
    });

});
