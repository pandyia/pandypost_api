<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dados do perfil editados pelo próprio usuário (tela "Meu perfil").
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('bio', 160)->nullable()->after('phone');
            $table->string('timezone', 64)->default('America/Sao_Paulo')->after('bio');
            // Caminho do arquivo no S3; só a URL assinada é exposta (MeResource).
            $table->string('avatar_path')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'bio', 'timezone', 'avatar_path']);
        });
    }
};
