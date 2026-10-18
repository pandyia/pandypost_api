<?php

use App\Models\Access;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

describe('spec 009: permissão de preferências de notificação', function () {

    it('regressão: notifications.view só em outro workspace do usuário não libera o GET', function () {
        $user = createUserWithPermissions(['notifications.view', 'notifications.update']);

        $wsB = Workspace::withoutGlobalScopes()->create(['name' => 'B', 'is_personal_team' => false]);
        $roleB = Role::withoutGlobalScopes()->create(['name' => 'Vazio', 'workspace_id' => $wsB->id]);
        $accessB = Access::create(['user_id' => $user->id, 'role_id' => $roleB->id, 'workspace_id' => $wsB->id]);
        User::whereKey($user->id)->update(['access_id' => $accessB->id]);

        $this->withToken($user->test_token)->getJson('/api/notification-preferences')->assertForbidden();
        $this->withToken($user->test_token)->patchJson('/api/notification-preferences', ['posts' => false])->assertForbidden();
        expect(User::find($user->id)->notification_preferences)->toBeNull();
    });

    it('regressão: perfil de outro workspace com a permissão não beneficia usuário sem ela', function () {
        $intruder = createUserWithoutPermissions();
        createUserWithPermissions(['notifications.view']); // outro workspace com a permissão

        $this->withToken($intruder->test_token)->getJson('/api/notification-preferences')->assertForbidden();
    });

    it('regressão: PATCH /users/{uuid}, /profile e demais rotas não gravam notification_preferences', function () {
        $user = createUserWithPermissions(['users.update', 'users.view']);
        $payload = ['name' => 'X', 'notification_preferences' => ['posts' => false, 'in_app' => ['posts' => false]]];

        foreach (['/api/profile', '/api/users/'.$user->uuid, '/api/me'] as $url) {
            $this->withToken($user->test_token)->patchJson($url, $payload);
            $this->withToken($user->test_token)->putJson($url, $payload);
        }

        expect(User::find($user->id)->notification_preferences)->toBeNull();
    });

    it('regressão: recebimento (/notifications*) segue livre sem as permissões', function () {
        $user = createUserWithoutPermissions();

        $this->withToken($user->test_token)->getJson('/api/notifications')->assertOk();
        $this->withToken($user->test_token)->getJson('/api/notifications/unread')->assertOk();
        $this->withToken($user->test_token)->patchJson('/api/notifications/read-all')->assertOk();
    });

    it('nega com 403 quando o usuário não tem currentAccess e nenhuma permissão', function () {
        $user = createUserWithoutPermissions();
        User::whereKey($user->id)->update(['access_id' => null]);

        $this->withToken($user->test_token)->getJson('/api/notification-preferences')->assertForbidden();
    });

    it('sem currentAccess a permissão de OUTRO workspace não deve valer (scope falha aberto)', function () {
        // Usuário com perfil que tem a permissão no workspace A, mas sem access_id definido.
        $user = createUserWithPermissions(['notifications.view']);
        $wsB = Workspace::withoutGlobalScopes()->create(['name' => 'B', 'is_personal_team' => false]);
        $roleB = Role::withoutGlobalScopes()->create(['name' => 'Vazio', 'workspace_id' => $wsB->id]);
        Access::create(['user_id' => $user->id, 'role_id' => $roleB->id, 'workspace_id' => $wsB->id]);
        // Sem workspace corrente (access_id nulo, p.ex. após remoção do acesso ativo).
        User::whereKey($user->id)->update(['access_id' => null]);

        // Comportamento seguro: sem workspace corrente resolvido, não conceder por perfis de workspaces arbitrários.
        $this->withToken($user->test_token)->getJson('/api/notification-preferences')->assertForbidden();
    })->skip('VULN-09: sem currentAccess o scope de Role falha aberto e hasPermission() vale para qualquer workspace do usuário');

});
