<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Quotation Details" box on a printed quotation: a branch keeps the
     * standard lines for it, and each quotation can carry its own.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->text('quotation_details')->nullable()->after('quotation_terms');
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->text('details')->nullable()->after('terms');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('quotation_details');
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
    }
};
