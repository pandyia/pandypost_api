<?php

namespace Database\Seeders;

use App\Models\Access;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'gabriel.m.correa.s@gmail.com')->first();
        $adminRole = Role::where('name', 'Admin')->first();
        $workspace = Workspace::where('name', 'Área de Trabalho')->first();

        if ($user && $adminRole && $workspace) {
            Access::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $adminRole->id,
                    'workspace_id' => $workspace->id,
                ],
                [
                    'user_id' => $user->id,
                    'role_id' => $adminRole->id,
                    'workspace_id' => $workspace->id,
                ]
            );
        }
    }
}
