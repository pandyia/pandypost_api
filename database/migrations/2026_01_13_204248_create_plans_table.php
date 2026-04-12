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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'Starter', 'Pro', 'Agency'
            $table->string('slug')->unique(); // 'starter', 'pro', 'agency'
            $table->string('description')->nullable();

            // Configurações de Limites (O "recheio" do SaaS)
            $table->integer('monthly_posts_limit')->default(10);
            $table->integer('social_accounts_limit')->default(3);

            // Valores
            $table->decimal('price', 8, 2)->default(0.00);
            $table->string('stripe_plan_id')->nullable(); // ID do produto no Stripe/Gateway

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
