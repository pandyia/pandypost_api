<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accesses', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->foreignId('role_id')->change()->constrained('roles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('accesses', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->foreignId('role_id')->change()->constrained('roles');
        });
    }
};
