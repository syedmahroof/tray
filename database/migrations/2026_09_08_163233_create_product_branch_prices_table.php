<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One price row per product and branch, holding the cost/MRP basis plus the
     * SR, PR and CR rate tiers together with their tax-inclusive counterparts.
     */
    public function up(): void
    {
        Schema::create('product_branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost', 12, 4)->nullable();
            $table->decimal('mrp', 12, 2)->nullable();

            foreach (['sr', 'pr', 'cr'] as $tier) {
                $table->decimal("{$tier}_discount", 5, 2)->nullable();
                $table->decimal("{$tier}_rate", 12, 2)->nullable();
                $table->decimal("{$tier}_rate_with_tax", 12, 2)->nullable();
            }

            $table->date('effective_from')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']);
            $table->index('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_branch_prices');
    }
};
