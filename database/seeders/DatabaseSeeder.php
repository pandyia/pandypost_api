<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();


        $this->call([

            PlanSeeder::class,
            // RoleSeeder::class, // Obsoleto pois as Roles agora pertencem a Workspaces no UserSeeder
            PermissionSeeder::class,

            // TenantSeeder::class,
            UserSeeder::class,
                // WorkspaceSeeder::class,
            AccessSeeder::class,
            YouTubeCategorySeeder::class,
            // RolePermissionSeeder::class, // Obsoleto pois as Permissões são atreladas diretamente na criação do Workspace/Role

            // ScheduledPostSeeder::class,
        ]);

    }
}
