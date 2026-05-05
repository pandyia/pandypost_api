<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_pipelines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_post_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('platform')->nullable(); // youtube, instagram, tiktok
            $table->enum('stage', ['idea', 'script', 'recorded', 'editing', 'ready', 'scheduled'])->default('idea');
            $table->date('due_date')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Optimises board queries: fetch all cards for a workspace grouped by stage
            $table->index(['workspace_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pipelines');
    }
};
