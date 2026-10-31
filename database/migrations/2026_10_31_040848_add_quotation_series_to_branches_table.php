<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each branch can number its quotations in its own series: a prefix and
     * the next number to hand out. Branches without a prefix keep the
     * company-wide QT-YYYY-NNNNN numbering.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('quotation_prefix', 50)->nullable()->after('quotation_terms');
            $table->unsignedInteger('quotation_next_number')->default(1)->after('quotation_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['quotation_prefix', 'quotation_next_number']);
        });
    }
};
