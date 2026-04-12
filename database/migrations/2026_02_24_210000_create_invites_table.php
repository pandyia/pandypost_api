<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('email')->index();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('PENDING');
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['email', 'workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
