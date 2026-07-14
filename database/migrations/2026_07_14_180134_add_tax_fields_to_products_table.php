<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn_code')->nullable()->after('name');
            $table->string('tax_type')->nullable()->after('price');
            $table->decimal('tax_percentage', 5, 2)->default(0)->after('tax_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'tax_type', 'tax_percentage']);
        });
    }
};
