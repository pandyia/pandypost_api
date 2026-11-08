<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O enum nativo da coluna não aceita o status novo `awaiting_upload`: vira string (a regra fica no enum da aplicação).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE scheduled_posts DROP CONSTRAINT IF EXISTS scheduled_posts_status_check');
        }

        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });

        Schema::table('scheduled_posts', function (Blueprint $table) {
            // Quando o envio do vídeo terminou (confirmado por upload-complete).
            $table->timestamp('upload_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->dropColumn('upload_completed_at');
        });
    }
};
