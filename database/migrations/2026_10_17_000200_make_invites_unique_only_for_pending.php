<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A unicidade (email, workspace_id, status) impedia ter dois convites
     * recusados/expirados/aceitos para o mesmo email e workspace. A regra de
     * negócio é só "um convite PENDING por email e workspace".
     */
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropUnique(['email', 'workspace_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX invites_email_workspace_id_pending_unique ON invites (email, workspace_id) WHERE status = 'PENDING'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX invites_email_workspace_id_pending_unique');

        Schema::table('invites', function (Blueprint $table) {
            $table->unique(['email', 'workspace_id', 'status']);
        });
    }
};
