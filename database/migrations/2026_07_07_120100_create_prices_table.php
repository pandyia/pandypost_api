<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();

            $table->integer('amount'); // em centavos
            $table->string('currency');  // App\Enums\Currency
            $table->string('frequency'); // App\Enums\BillingFrequency
            $table->integer('trial_period_days')->default(0);

            $table->string('gateway_price_id')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Consultas por combinação moeda/frequência dentro do plano.
            $table->index(['plan_id', 'currency', 'frequency', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
