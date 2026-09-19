<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record what each price row's tier percentages are worked out from, which
     * until now was inferred from whether the row carried an MRP.
     */
    public function up(): void
    {
        Schema::table('product_branch_prices', function (Blueprint $table): void {
            $table->string('rate_basis', 4)->default('mrp')->after('mrp');
        });

        DB::table('product_branch_prices')
            ->where(fn ($query) => $query->whereNull('mrp')->orWhere('mrp', '<=', 0))
            ->update(['rate_basis' => 'cost']);
    }

    public function down(): void
    {
        Schema::table('product_branch_prices', function (Blueprint $table): void {
            $table->dropColumn('rate_basis');
        });
    }
};
