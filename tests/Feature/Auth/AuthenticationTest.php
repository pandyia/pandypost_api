<?php

use App\Models\User;
use App\Models\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

describe('cadastro', function () {
    it('deve criar um usuário com dados válidos', function () {
        $result = signupUser();

        $result['response']->assertCreated()
            ->assertJsonStructure([
                'user' => ['uuid', 'name', 'email'],
                'token',
                'status',
            ])
            ->assertJsonPath('status', 'unverified');

        $this->assertDatabaseHas('users', [
            'email' => 'teste@gmail.com',
        ]);

        expect($result['user']->hasVerifiedEmail())->toBeFalse();
    });

    it('deve retornar erros de validação com dados inválidos', function () {
        $this->postJson('/api/auth/signup', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    it('deve retornar erro de validação quando o email já está cadastrado', function () {
        User::factory()->create(['email' => 'existing@email.com']);

        $this->postJson('/api/auth/signup', [
            'name' => 'Another User',
            'email' => 'existing@email.com',
            'password' => '12345678G',
            'password_confirmation' => '12345678G',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });
});

describe('login', function () {
    it('deve logar com sucesso com usuário verificado', function () {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['user', 'token', 'current_workspace']);
    });

    it('deve retornar erro quando o email não está verificado', function () {
        $user = User::factory()->unverified()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(403)
            ->assertJson(['error' => 'email_not_verified']);
    });

    it('deve retornar não autorizado quando a senha está errada', function () {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_credentials']);
    });

    it('deve retornar não encontrado quando o email não existe', function () {
        $this->postJson('/api/login', [
            'email' => 'naoexiste@email.com',
            'password' => 'password',
        ])
            ->assertStatus(404)
            ->assertJson(['error' => 'user_not_found']);
    });

    it('deve logar no workspace pessoal se access_id for nulo', function () {
         $user = createUserWithPermissions(['workspaces.view'], true);
        
        unset($user->test_token); 
        $user->update(['access_id' => null]);
        
        $personalWorkspaceUuid = $user->accesses()
            ->whereHas('workspace', fn($q) => $q->where('is_personal_team', true))
            ->first()
            ->workspace
            ->uuid;

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('current_workspace.uuid', $personalWorkspaceUuid);

        expect($user->fresh()->access_id)->not->toBeNull();
    });

    it('deve logar no workspace determinado se access_id não for nulo', function () {
        $user = createUserWithPermissions(['workspaces.view'], false);
        unset($user->test_token); 
        $customAccess = $user->accesses()->first();
        $user->update(['access_id' => $customAccess->id]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('current_workspace.uuid', $customAccess->workspace->uuid);

        expect($user->fresh()->access_id)->toBe($customAccess->id);
    });
});

describe('expiração do token', function () {
    it('deve ter expiração de 30 dias quando remember_me é true', function () {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember_me' => true,
        ])->assertOk();

        $token = $user->tokens()->latest()->first();
        $diff = $token->expires_at->diffInMinutes(now()->addDays(30));

        expect($diff)->toBeLessThan(5);
    });

    it('deve ter expiração padrão de 2 horas sem remember_me', function () {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $user->tokens()->latest()->first();
        $diff = $token->expires_at->diffInMinutes(now()->addHours(2));

        expect($diff)->toBeLessThan(5);
    });
});

describe('verificação de email', function () {
    beforeEach(function () {
        $result = signupUser();
        $this->authUser = $result['user'];
        $this->authToken = $result['token'];
        $this->withHeader('Authorization', "Bearer {$this->authToken}");
    });

    it('deve confirmar email com token válido', function () {
        $this->postJson('/api/auth/confirm-email', [
            'token' => $this->authUser->verificationEmailToken->token,
        ])
            ->assertOk()
            ->assertJson(['message' => 'Email verificado com sucesso.']);

        $this->authUser->refresh();
        expect($this->authUser->hasVerifiedEmail())->toBeTrue();
    });

    it('deve reenviar email de verificação com novo token', function () {
        $oldToken = $this->authUser->verificationEmailToken->token;

        $this->postJson('/api/auth/resend-email-verification')
            ->assertOk()
            ->assertJson(['message' => 'Código reenviado com sucesso.']);

        $this->authUser->refresh();
        expect($this->authUser->verificationEmailToken->token)->not()->toBe($oldToken);
    });
});

describe('recuperação de senha', function () {
    it('deve enviar link de recuperação quando o email existe', function () {
        User::factory()->create(['email' => 'teste@gmail.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'teste@gmail.com',
        ])->assertOk()
            ->assertJson(['message' => 'Se o email existir, um link de recuperação será enviado.']);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'teste@gmail.com',
        ]);
    });

    it('deve resetar a senha com token válido', function () {
        $user = User::factory()->create([
            'email' => 'teste@gmail.com',
            'password' => 'senhaAntiga123',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'teste@gmail.com',
        ]);

        $resetToken = ResetPassword::where('email', 'teste@gmail.com')->first();

        $this->postJson('/api/auth/password-reset', [
            'token' => $resetToken->token,
            'email' => 'teste@gmail.com',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Senha redefinida com sucesso.']);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'teste@gmail.com',
        ]);

        // Login com nova senha funciona
        $this->postJson('/api/login', [
            'email' => 'teste@gmail.com',
            'password' => 'novaSenha123',
        ])->assertOk();

        // Login com senha antiga não funciona
        $this->postJson('/api/login', [
            'email' => 'teste@gmail.com',
            'password' => 'senhaAntiga123',
        ])->assertStatus(401);
    });
});

describe('fluxo completo de autenticação', function () {
    it('deve completar cadastro → login → confirmar email → recuperar → resetar → login', function () {
        // 1. Signup
        $result = signupUser();
        $result['response']->assertCreated();

        // 2. Login (não verificado — deve ser bloqueado)
        $this->postJson('/api/login', [
            'email' => 'teste@gmail.com',
            'password' => '12345678G',
        ])
            ->assertStatus(403)
            ->assertJson(['error' => 'email_not_verified']);

        // 3. Confirma email (rota pública, não precisa de auth)
        $this->postJson('/api/auth/confirm-email', [
                'token' => $result['user']->verificationEmailToken->token,
            ])
            ->assertOk();

        $result['user']->refresh();
        expect($result['user']->hasVerifiedEmail())->toBeTrue();

        // 4. Login verificado retorna workspace
        $this->postJson('/api/login', [
            'email' => 'teste@gmail.com',
            'password' => '12345678G',
        ])
            ->assertOk()
            ->assertJsonStructure(['user', 'token', 'current_workspace']);

        // 5. Solicita recuperação de senha
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'teste@gmail.com',
        ])->assertOk();

        // 6. Reseta a senha
        $resetToken = ResetPassword::where('email', 'teste@gmail.com')->first();

        $this->postJson('/api/auth/password-reset', [
            'token' => $resetToken->token,
            'email' => 'teste@gmail.com',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertOk();

        // 7. Login com nova senha
        $this->postJson('/api/login', [
            'email' => 'teste@gmail.com',
            'password' => 'novaSenha123',
        ])->assertOk();

        // 8. Login com senha antiga falha
        $this->postJson('/api/login', [
            'email' => 'teste@gmail.com',
            'password' => '12345678G',
        ])->assertStatus(401);
    });
});