<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        Workspace::firstOrCreate(
            ['name' => 'Área de Trabalho'],
            ['name' => 'Área de Trabalho', 'is_personal_team' => true]
        );
    }
}
