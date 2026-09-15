<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every product a unique part number and a unit of measure, and drop
     * the branch ownership: products are now global and only their prices are
     * branch specific.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
            $table->string('unit', 20)->nullable()->after('name');
        });

        DB::table('products')->whereNull('code')->orderBy('id')->each(function (object $product) {
            DB::table('products')
                ->where('id', $product->id)
                ->update(['code' => 'PRD-'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT)]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'unit']);
        });
    }
};
