<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->string('media_path')->nullable()->change();
            $table->string('platform_post_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->string('media_path')->nullable(false)->change();
            $table->string('platform_post_id')->nullable(false)->change();
        });
    }
};
