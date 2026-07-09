<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabela custom de pagamentos (1 linha por invoice/checkout), populada
     * exclusivamente pelo listener/job de webhook. Os índices únicos nos
     * gateway_*_id são a garantia de idempotência no nível do banco.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status')->default('pending'); // App\Enums\PaymentStatus
            $table->string('method')->nullable();          // credit_card / boleto
            $table->integer('amount')->default(0);         // centavos
            $table->string('currency')->nullable();

            // Correlação com o gateway (idempotência via unique parcial)
            $table->string('gateway_checkout_session_id')->nullable()->unique();
            $table->string('gateway_checkout_url')->nullable();
            $table->timestamp('gateway_checkout_expires_at')->nullable();
            $table->string('gateway_invoice_id')->nullable()->unique();
            $table->string('gateway_hosted_invoice_url')->nullable();
            $table->string('gateway_invoice_pdf')->nullable();
            $table->string('gateway_payment_intent_id')->nullable()->unique();
            $table->string('receipt_url')->nullable();

            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
