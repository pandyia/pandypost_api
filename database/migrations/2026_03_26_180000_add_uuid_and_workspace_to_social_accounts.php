<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn(['uuid', 'workspace_id']);
        });
    }
};
