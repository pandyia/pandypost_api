<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Snapshot imutável de um preço antes de ser substituído (preços do Stripe
     * são imutáveis: "editar" = arquivar o antigo + criar um novo). Guarda o
     * estado anterior para histórico/auditoria.
     */
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('price_id')->constrained()->cascadeOnDelete();

            $table->string('gateway_price_id')->nullable();
            $table->integer('amount'); // em centavos
            $table->string('currency');
            $table->string('frequency');
            $table->integer('trial_period_days')->default(0);

            $table->timestamp('archived_at')->nullable();
            $table->string('reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
