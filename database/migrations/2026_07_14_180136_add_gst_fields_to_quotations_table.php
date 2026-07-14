<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('gstin')->nullable()->after('builder_id');
            $table->string('supply_type')->default('intra')->after('gstin');
            $table->decimal('cgst_amount', 12, 2)->default(0)->after('tax_amount');
            $table->decimal('sgst_amount', 12, 2)->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 12, 2)->default(0)->after('sgst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['gstin', 'supply_type', 'cgst_amount', 'sgst_amount', 'igst_amount']);
        });
    }
};
