<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Adiciona as colunas da gestão de financeiro (Admin) ao plano.
     * As colunas legadas (monthly_posts_limit, social_accounts_limit, price,
     * stripe_plan_id) permanecem até a fase Cliente refazer o fluxo de assinatura.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->boolean('is_visible')->default(true)->after('description');
            $table->string('gateway_product_id')->nullable()->after('stripe_plan_id');
            $table->softDeletes();
        });

        // Backfill de uuid para eventuais planos já existentes.
        foreach (DB::table('plans')->whereNull('uuid')->get() as $plan) {
            DB::table('plans')->where('id', $plan->id)->update(['uuid' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'is_visible', 'gateway_product_id']);
            $table->dropSoftDeletes();
        });
    }
};
