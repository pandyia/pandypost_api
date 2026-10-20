<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            // Quando o post falhou (conta o prazo de retenção da mídia) e quando a mídia foi limpa.
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('media_purged_at')->nullable();
        });

        // Posts que já estavam com falha: o prazo conta a partir da última alteração.
        DB::table('scheduled_posts')
            ->where('status', 'failed')
            ->update(['failed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->dropColumn(['failed_at', 'media_purged_at']);
        });
    }
};
