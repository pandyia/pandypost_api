<?php

namespace Database\Seeders;

use App\Models\Permission;

use App\Models\Access;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Permissions are now handled by Gate::before in AppServiceProvider.
        // We do NOT sync database permissions to keep the table clean and performant.

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@pandypost.com',
                'is_super_admin' => true,
                'role' => 'Super Admin',
            ],
            [
                'name' => 'João Victor',
                'email' => 'joao@gmail.com',
                'is_super_admin' => false,
                'role' => 'Admin',
            ],
            [
                'name' => 'Arthur Vieira',
                'email' => 'arthur@gmail.com',
                'is_super_admin' => false,
                'role' => 'Admin',
            ],
            [
                'name' => 'Antônio Neri',
                'email' => 'antonio@gmail.com',
                'is_super_admin' => false,
                'role' => 'Admin',
            ],
            [
                'name' => 'Gabriel Moreira',
                'email' => 'gabriel@gmail.com',
                'is_super_admin' => false,
                'role' => 'Admin',
            ],
            [
                'name' => 'Laís Nascimento',
                'email' => 'lais@gmail.com',
                'is_super_admin' => false,
                'role' => 'Admin',
            ],
            [
                'name' => 'Letícia',
                'email' => 'leticia@gmail.com',
                'is_super_admin' => false,
                'role' => 'Admin',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $userData['name'],
                    'password' => Hash::make('123456'),
                    'is_super_admin' => $userData['is_super_admin'],
                    'email_verified_at' => now(),
                ]
            );

            $workspaceName = $userData['name'] === 'Super Admin' ? 'Super Admin Workspace' : $userData['name'] . ' Workspace';

            $workspace = Workspace::firstOrCreate(
                ['name' => $workspaceName],
                [
                    'name' => $workspaceName,
                    'uuid' => (string) Str::uuid(),
                    'is_personal_team' => true,
                ]
            );

            $role = Role::firstOrCreate([
                'uuid' => (string) Str::uuid(),
                'name' => $userData['role'],
                'workspace_id' => $workspace->id,
            ]);

            // Popula o front-end carregando as permissões para o banco
            if ($role->name === 'Admin') {
                $role->permissions()->sync(Permission::pluck('id')->toArray());
            }

            Access::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'workspace_id' => $workspace->id,
                ],
                [
                    'role_id' => $role->id,
                ]
            );

            // Nota: assinaturas agora são do Cashier (keyed pelo Workspace, exigem
            // stripe_id) e são criadas pelo fluxo de checkout do Stripe — não pelo
            // seed. A tabela legada `subscriptions` (user_id/posts_used) foi removida.
        }
    }
}
