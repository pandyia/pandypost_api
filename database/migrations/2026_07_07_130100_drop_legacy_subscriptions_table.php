<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A tabela `subscriptions` legada (ligada a user_id, com posts_used/limit)
     * é resíduo do modelo antigo e será substituída pela tabela do Cashier
     * (ligada ao Workspace). Rebuild limpo, sem migração de dados.
     */
    public function up(): void
    {
        Schema::dropIfExists('subscriptions');
    }

    /**
     * Recria a estrutura legada para reversibilidade da migration.
     */
    public function down(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->enum('status', ['active', 'past_due', 'canceled'])->default('active');
            $table->integer('posts_limit')->default(0);
            $table->integer('posts_used')->default(0);
            $table->timestamps();
        });
    }
};
