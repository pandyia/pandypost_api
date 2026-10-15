<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto diária das métricas da conta (Instagram/TikTok não guardam histórico).
        Schema::create('social_account_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('followers')->nullable();
            $table->unsignedBigInteger('following')->nullable();
            $table->unsignedBigInteger('media_count')->nullable();
            $table->unsignedBigInteger('total_likes')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_account_metric_snapshots');
    }
};
