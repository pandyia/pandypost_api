<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->string('platform_post_id')->nullable();
            $table->string('container_id')->nullable()->after('payload');
            $table->timestamp('container_created_at')->nullable()->after('container_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->dropColumn(['platform_post_id', 'container_id', 'container_created_at']);
        });
    }
};
