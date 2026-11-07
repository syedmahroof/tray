<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            // Each line is priced on its own tier (SR / PR / CR) or at a custom
            // percentage: a discount off MRP, or a markup on cost (plus any
            // freight), before any agent commission is spread over it.
            $table->string('rate_tier', 10)->nullable()->after('quantity');
            $table->string('price_basis', 4)->nullable()->after('rate_tier');
            $table->decimal('list_price', 12, 2)->nullable()->after('price_basis');
            $table->decimal('price_percent', 7, 2)->nullable()->after('list_price');
            $table->decimal('base_price', 12, 2)->nullable()->after('price_percent');
        });

        Schema::table('quotations', function (Blueprint $table) {
            // Added to the line prices for an agent buying on a company's
            // behalf; shown in the app, never on the printed quotation.
            $table->decimal('agent_commission', 12, 2)->default(0)->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['rate_tier', 'price_basis', 'list_price', 'price_percent', 'base_price']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('agent_commission');
        });
    }
};
