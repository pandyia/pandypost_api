<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

describe('login grava remember_me', function () {

    it('remember_me true grava a flag e usa a expiração em dias', function () {
        $user = User::factory()->create(['password' => 'password', 'remember_me' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password', 'remember_me' => true])
            ->assertOk()
            ->assertJsonPath('user.remember_me', true);

        $expiresAt = $user->tokens()->latest()->first()->expires_at;

        expect($user->fresh()->remember_me)->toBeTrue()
            ->and($expiresAt->diffInMinutes(now()->addDays((int) config('auth.token_expiration_days'))))->toBeLessThan(5);
    });

    it('remember_me false (ou ausente) grava a flag e usa a expiração em minutos', function () {
        $user = User::factory()->create(['password' => 'password', 'remember_me' => true]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.remember_me', false);

        $expiresAt = $user->tokens()->latest()->first()->expires_at;

        expect($user->fresh()->remember_me)->toBeFalse()
            ->and($expiresAt->diffInMinutes(now()->addMinutes((int) config('sanctum.expiration'))))->toBeLessThan(5);
    });

    it('login com senha errada não altera remember_me', function () {
        $user = User::factory()->create(['password' => 'password', 'remember_me' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'errada', 'remember_me' => true])
            ->assertStatus(401);

        expect($user->fresh()->remember_me)->toBeFalse();
    });
});
