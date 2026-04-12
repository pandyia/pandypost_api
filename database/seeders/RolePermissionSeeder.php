<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();

        if ($adminRole) {
            $allPermissions = Permission::all();
            $adminRole->permissions()->syncWithoutDetaching($allPermissions->pluck('id'));
        }
    }
}
