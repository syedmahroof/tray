<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('hsn_code')->nullable()->after('description');
            $table->decimal('tax_percentage', 5, 2)->default(0)->after('unit_price');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'tax_percentage', 'tax_amount']);
        });
    }
};
