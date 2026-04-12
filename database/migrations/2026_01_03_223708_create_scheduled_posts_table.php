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
        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('platform'); // 'youtube', 'tiktok', 'instagram'
            $table->string('media_path'); // Onde o arquivo está salvo (storage/app/...)
            $table->text('caption')->nullable();
            $table->string('title')->nullable(); // Útil para YouTube
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending', 'processing', 'published', 'failed', 'cancelled'])->default('pending');
            $table->json('payload')->nullable(); // Para guardar IDs extras ou configurações específicas
            $table->dateTime('published_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_posts');
    }
};
