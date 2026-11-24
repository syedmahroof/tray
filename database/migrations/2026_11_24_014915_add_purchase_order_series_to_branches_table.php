<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each branch can number its purchase orders in its own series, the same
     * way it numbers its quotations: a prefix and the next number to issue.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('purchase_order_prefix', 50)->nullable()->after('quotation_next_number');
            $table->unsignedInteger('purchase_order_next_number')->default(1)->after('purchase_order_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['purchase_order_prefix', 'purchase_order_next_number']);
        });
    }
};
