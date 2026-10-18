<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Caminho da foto no storage; a API só expõe a URL assinada (`avatar`).
            $table->string('avatar_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
