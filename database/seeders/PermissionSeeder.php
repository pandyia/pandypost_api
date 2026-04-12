<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Usuários
            ['name' => 'users.view', 'description' => 'Visualizar usuários', 'group' => 'users'],
            ['name' => 'users.create', 'description' => 'Criar usuários', 'group' => 'users'],
            ['name' => 'users.update', 'description' => 'Editar usuários', 'group' => 'users'],
            ['name' => 'users.delete', 'description' => 'Excluir usuários', 'group' => 'users'],
            ['name' => 'users.change_role', 'description' => 'Alterar perfil do usuário', 'group' => 'users'],

            // Posts Agendados
            ['name' => 'posts.view', 'description' => 'Visualizar posts agendados', 'group' => 'posts'],
            ['name' => 'posts.create', 'description' => 'Criar posts agendados', 'group' => 'posts'],
            ['name' => 'posts.update', 'description' => 'Editar posts agendados', 'group' => 'posts'],
            ['name' => 'posts.delete', 'description' => 'Excluir posts agendados', 'group' => 'posts'],

            // Workspaces
            ['name' => 'workspaces.view', 'description' => 'Visualizar workspaces', 'group' => 'workspaces'],
            ['name' => 'workspaces.create', 'description' => 'Criar workspaces', 'group' => 'workspaces'],
            ['name' => 'workspaces.update', 'description' => 'Editar workspaces', 'group' => 'workspaces'],
            ['name' => 'workspaces.delete', 'description' => 'Excluir workspaces', 'group' => 'workspaces'],

            // Perfis (Roles)
            ['name' => 'roles.view', 'description' => 'Visualizar perfis', 'group' => 'roles'],
            ['name' => 'roles.create', 'description' => 'Criar perfis', 'group' => 'roles'],
            ['name' => 'roles.update', 'description' => 'Editar perfis', 'group' => 'roles'],
            ['name' => 'roles.delete', 'description' => 'Excluir perfis', 'group' => 'roles'],

            // Permissões
            ['name' => 'permissions.view', 'description' => 'Visualizar permissões', 'group' => 'permissions'],

            // Acessos
            ['name' => 'accesses.view', 'description' => 'Visualizar acessos', 'group' => 'accesses'],
            ['name' => 'accesses.create', 'description' => 'Criar acessos', 'group' => 'accesses'],
            ['name' => 'accesses.update', 'description' => 'Editar acessos', 'group' => 'accesses'],
            ['name' => 'accesses.delete', 'description' => 'Excluir acessos', 'group' => 'accesses'],

            // Contas Sociais
            ['name' => 'social_accounts.view', 'description' => 'Visualizar contas sociais', 'group' => 'social_accounts'],
            ['name' => 'social_accounts.connect', 'description' => 'Conectar contas sociais', 'group' => 'social_accounts'],
            ['name' => 'social_accounts.disconnect', 'description' => 'Desconectar contas sociais', 'group' => 'social_accounts'],

            // Faturamento
            ['name' => 'billing.view', 'description' => 'Visualizar faturamento', 'group' => 'billing'],
            ['name' => 'billing.manage', 'description' => 'Gerenciar assinaturas', 'group' => 'billing'],

            // Configurações
            ['name' => 'settings.view', 'description' => 'Visualizar configurações', 'group' => 'settings'],
            ['name' => 'settings.update', 'description' => 'Alterar configurações', 'group' => 'settings'],

            // Convites
            ['name' => 'invites.create', 'description' => 'Enviar convites', 'group' => 'invites'],
            ['name' => 'invites.view', 'description' => 'Visualizar convites', 'group' => 'invites'],
            ['name' => 'invites.delete', 'description' => 'Excluir convites', 'group' => 'invites'],

            // Auditoria
            ['name' => 'logs.view', 'description' => 'Visualizar logs de auditoria', 'group' => 'logs'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
