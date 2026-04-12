<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['Admin'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName], ['name' => $roleName, 'uuid' => Str::uuid()]);
        }
    }
}
