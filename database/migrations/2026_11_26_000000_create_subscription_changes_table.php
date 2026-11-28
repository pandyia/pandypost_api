<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mudança de assinatura agendada para o fim do ciclo (downgrade), espelho local do
     * Subscription Schedule do Stripe. O índice único parcial garante no máximo uma `pending`
     * por assinatura (sintaxe válida em PostgreSQL e SQLite).
     */
    public function up(): void
    {
        Schema::create('subscription_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_id')->constrained();
            $table->string('stripe_schedule_id')->unique();
            $table->timestamp('effective_at');
            $table->string('status')->default('pending'); // App\Enums\SubscriptionChangeStatus
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX subscription_changes_one_pending_per_subscription ON subscription_changes (subscription_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_changes');
    }
};
