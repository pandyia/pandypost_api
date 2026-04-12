<?php

use App\Models\Access;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Notification::fake(); // Prevent real notifications from being sent
});

// ---------------------------------------------------------------------------
// ENVIAR CONVITES (store/send)
// ---------------------------------------------------------------------------

describe('enviar convite', function () {

    it('envia um convite com sucesso para um usuário existente', function () {
        $user = createUserWithPermissions(['invites.create']);
        $workspaceId = $user->currentAccess->workspace_id;
        
        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Cargo Teste',
            'workspace_id' => $workspaceId,
        ]);
        
        $invitedUser = User::factory()->create(['email' => 'convidado@teste.com']);

        $this->withToken($user->test_token)
            ->postJson('/api/invites', [
                'email' => 'convidado@teste.com',
                'role_uuid' => $role->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Convite enviado com sucesso');

        $this->assertDatabaseHas('invites', [
            'email' => 'convidado@teste.com',
            'workspace_id' => $workspaceId,
            'role_id' => $role->id,
            'invited_by' => $user->id,
            'status' => Invite::STATUS_PENDING,
        ]);
    });

    it('envia um convite com sucesso para um email não cadastrado no sistema', function () {
        $user = createUserWithPermissions(['invites.create']);
        $workspaceId = $user->currentAccess->workspace_id;
        
        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Cargo Teste',
            'workspace_id' => $workspaceId,
        ]);

        $this->withToken($user->test_token)
            ->postJson('/api/invites', [
                'email' => 'novo_usuario@teste.com',
                'role_uuid' => $role->uuid,
            ])
            ->assertOk();

        $this->assertDatabaseHas('invites', [
            'email' => 'novo_usuario@teste.com',
            'workspace_id' => $workspaceId,
        ]);
    });

    it('retorna 409 quando o usuário já possui um convite pendente para o mesmo workspace', function () {
        $user = createUserWithPermissions(['invites.create']);
        $workspaceId = $user->currentAccess->workspace_id;
        
        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Cargo',
            'workspace_id' => $workspaceId,
        ]);

        Invite::factory()->pending()->create([
            'email' => 'convidado@teste.com',
            'workspace_id' => $workspaceId,
            'invited_by' => $user->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($user->test_token)
            ->postJson('/api/invites', [
                'email' => 'convidado@teste.com',
                'role_uuid' => $role->uuid,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_invited');
    });

    it('retorna 422 quando o cargo (role_uuid) não existe', function () {
        $user = createUserWithPermissions(['invites.create']);

        $this->withToken($user->test_token)
            ->postJson('/api/invites', [
                'email' => 'convidado@teste.com',
                'role_uuid' => Str::uuid(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_uuid']);
    });

    it('retorna 403 quando o usuário não possui permissão', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)
            ->postJson('/api/invites', [
                'email' => 'qualquer@teste.com',
                'role_uuid' => Str::uuid(),
            ])
            ->assertForbidden();
    });

    it('retorna 409 quando tenta enviar convite para um usuário que já é membro', function () {
        $user = createUserWithPermissions(['invites.create']);
        $workspaceId = $user->currentAccess->workspace_id;
        
        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Cargo Teste',
            'workspace_id' => $workspaceId,
        ]);
        
        $existingMember = User::factory()->create(['email' => 'membro@teste.com']);
        Access::create([
            'user_id' => $existingMember->id,
            'workspace_id' => $workspaceId,
            'role_id' => $role->id,
        ]);

        $this->withToken($user->test_token)
            ->postJson('/api/invites', [
                'email' => 'membro@teste.com',
                'role_uuid' => $role->uuid,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_member');
    });
});

// ---------------------------------------------------------------------------
// LISTAR CONVITES DO WORKSPACE (index)
// ---------------------------------------------------------------------------

describe('listar convites do workspace', function () {

    it('lista os convites enviados pelo workspace atual de forma paginada', function () {
        $user = createUserWithPermissions(['invites.view']);
        $workspaceId = $user->currentAccess->workspace_id;
        
        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Cargo',
            'workspace_id' => $workspaceId,
        ]);

        $invite = Invite::factory()->create([
            'email' => 'teste@gmail.com',
            'workspace_id' => $workspaceId,
            'invited_by' => $user->id,
            'role_id' => $role->id,
        ]);

        $response = $this->withToken($user->test_token)
            ->getJson('/api/invites')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'recipient' => ['email'], 'status', 'expires_at', 'role']
                ]
            ]);

        $uuids = collect($response->json('data'))->pluck('uuid');
        expect($uuids)->toContain($invite->uuid);
    });

    it('retorna 403 sem permissão para listar convites', function () {
        $user = createUserWithoutPermissions();
        $this->withToken($user->test_token)->getJson('/api/invites')->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// LISTAR CONVITES RECEBIDOS (me/invites)
// ---------------------------------------------------------------------------

describe('listar convites recebidos', function () {

    it('lista corretamente os convites direcionados ao email do usuário autenticado', function () {
        $authUser = User::factory()->create(['email' => 'meu_email@teste.com']);
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        // Um workspace externo convidou o authUser
        $otherWorkspace = Workspace::withoutGlobalScopes()->create(['name' => 'Outro Workspace', 'is_personal_team' => false]);
        $inviter = User::factory()->create();
        $role = Role::withoutGlobalScopes()->create(['name' => 'Especial', 'workspace_id' => $otherWorkspace->id]);

        $invite = Invite::factory()->create([
            'email' => 'meu_email@teste.com',
            'workspace_id' => $otherWorkspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $role->id,
        ]);

        $response = $this->withToken($authUser->test_token)
            ->getJson('/api/me/invites')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'status', 'workspace', 'role', 'invitedBy', 'created_at', 'expires_at']
                ]
            ]);

        $uuids = collect($response->json('data'))->pluck('uuid');
        expect($uuids)->toContain($invite->uuid);
    });
});

// ---------------------------------------------------------------------------
// ACEITAR CONVITE (accept)
// ---------------------------------------------------------------------------

describe('aceitar convite', function () {

    it('aceita um convite pendente, cria o acesso e vincula o cargo', function () {
        $authUser = User::factory()->create();
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Empresa', 'is_personal_team' => false]);
        $inviter = User::factory()->create();
        $role = Role::withoutGlobalScopes()->create(['name' => 'Membro', 'workspace_id' => $workspace->id]);

        $invite = Invite::factory()->pending()->create([
            'email' => $authUser->email,
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($authUser->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('message', 'Convite aceito com sucesso');

        $this->assertDatabaseHas('invites', [
            'id' => $invite->id,
            'status' => Invite::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('accesses', [
            'user_id' => $authUser->id,
            'workspace_id' => $workspace->id,
            'role_id' => $role->id,
        ]);
    });

    it('rejeita "softly" convites antigos já aceitos para o mesmo workspace ao aceitar um novo duplo convite', function () {
        $authUser = User::factory()->create();
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Empresa', 'is_personal_team' => false]);
        $inviter = User::factory()->create();
        $roleAntiga = Role::withoutGlobalScopes()->create(['name' => 'Leitor', 'workspace_id' => $workspace->id]);
        $roleNova = Role::withoutGlobalScopes()->create(['name' => 'Admin', 'workspace_id' => $workspace->id]);

        // Convite Antigo (já aceito antes)
        $oldInvite = Invite::factory()->accepted()->create([
            'email' => $authUser->email,
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $roleAntiga->id,
            'status' => Invite::STATUS_ACCEPTED,
        ]);

        // Novo convite pendente
        $newInvite = Invite::factory()->pending()->create([
            'email' => $authUser->email,
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $roleNova->id,
        ]);

        // Aceita o NOVO convite, o antigo deve virar DECLINED conforme a regra de negócio
        $this->withToken($authUser->test_token)
            ->patchJson("/api/me/invites/{$newInvite->uuid}/accept")
            ->assertOk();

        $this->assertDatabaseHas('invites', [
            'id' => $oldInvite->id,
            'status' => Invite::STATUS_DECLINED,
        ]);

        $this->assertDatabaseHas('invites', [
            'id' => $newInvite->id,
            'status' => Invite::STATUS_ACCEPTED,
        ]);
    });

    it('retorna 403 quando tenta aceitar o convite de email de outro usuário', function () {
        $authUser = User::factory()->create();
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Empresa', 'is_personal_team' => false]);
        $inviter = User::factory()->create();
        $role = Role::withoutGlobalScopes()->create(['name' => 'Membro', 'workspace_id' => $workspace->id]);

        // Convite p/ outra pessoa
        $invite = Invite::factory()->pending()->create([
            'email' => 'outra_pessoa@email.com',
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($authUser->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/accept")
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_your_invite');
    });

    it('retorna 400 quando tenta aceitar um convite que já expirou', function () {
        $authUser = User::factory()->create();
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Empresa', 'is_personal_team' => false]);
        $inviter = User::factory()->create();
        $role = Role::withoutGlobalScopes()->create(['name' => 'Membro', 'workspace_id' => $workspace->id]);

        $invite = Invite::factory()->expired()->create([
            'email' => $authUser->email,
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($authUser->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/accept")
            ->assertStatus(400)
            ->assertJsonPath('error', 'invite_not_pending');
    });

    it('retorna 409 quando o usuário já é membro atual do workspace', function () {
        $authUser = User::factory()->create();
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Empresa', 'is_personal_team' => false]);
        $role = Role::withoutGlobalScopes()->create(['name' => 'Membro', 'workspace_id' => $workspace->id]);

        // Ele já faz parte (tem Access criado)
        Access::create([
            'user_id' => $authUser->id,
            'workspace_id' => $workspace->id,
            'role_id' => $role->id,
        ]);

        $inviter = User::factory()->create();
        $invite = Invite::factory()->pending()->create([
            'email' => $authUser->email,
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($authUser->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/accept")
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_member');
    });
});

// ---------------------------------------------------------------------------
// REJEITAR CONVITE (decline)
// ---------------------------------------------------------------------------

describe('rejeitar convite', function () {

    it('rejeita o convite corretamente mudando o status para DECLINED', function () {
        $authUser = User::factory()->create();
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Empresa', 'is_personal_team' => false]);
        $inviter = User::factory()->create();
        $role = Role::withoutGlobalScopes()->create(['name' => 'Membro', 'workspace_id' => $workspace->id]);

        $invite = Invite::factory()->pending()->create([
            'email' => $authUser->email,
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($authUser->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/decline")
            ->assertOk()
            ->assertJsonPath('message', 'Convite rejeitado com sucesso');

        $this->assertDatabaseHas('invites', [
            'id' => $invite->id,
            'status' => Invite::STATUS_DECLINED, // Deve ser DECLINED
        ]);
        
        $this->assertDatabaseMissing('accesses', [
            'user_id' => $authUser->id,
            'workspace_id' => $workspace->id,
        ]);
    });

    it('retorna 403 quando tenta rejeitar o convite de email de outro usuário', function () {
        $authUser = User::factory()->create();
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Empresa', 'is_personal_team' => false]);
        $inviter = User::factory()->create();
        $role = Role::withoutGlobalScopes()->create(['name' => 'Membro', 'workspace_id' => $workspace->id]);

        $invite = Invite::factory()->pending()->create([
            'email' => 'outra_pessoa@email.com',
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($authUser->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/decline")
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_your_invite');
    });

    it('retorna 400 quando tenta rejeitar um convite que já expirou', function () {
        $authUser = User::factory()->create();
        $authUser->test_token = $authUser->createToken('test')->plainTextToken;

        $workspace = Workspace::withoutGlobalScopes()->create(['name' => 'Empresa', 'is_personal_team' => false]);
        $inviter = User::factory()->create();
        $role = Role::withoutGlobalScopes()->create(['name' => 'Membro', 'workspace_id' => $workspace->id]);

        $invite = Invite::factory()->expired()->create([
            'email' => $authUser->email,
            'workspace_id' => $workspace->id,
            'invited_by' => $inviter->id,
            'role_id' => $role->id,
        ]);

        $this->withToken($authUser->test_token)
            ->patchJson("/api/me/invites/{$invite->uuid}/decline")
            ->assertStatus(400)
            ->assertJsonPath('error', 'invite_not_pending');
    });
});
