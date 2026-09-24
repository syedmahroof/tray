<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each branch prints its own letterhead on the quotations it raises: the
     * name it trades under, its logo, contact details, GSTIN and the terms it
     * quotes on.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->after('city');
            $table->string('logo_path')->nullable()->after('company_name');
            $table->string('phone', 50)->nullable()->after('logo_path');
            $table->string('mobile', 50)->nullable()->after('phone');
            $table->string('email')->nullable()->after('mobile');
            $table->string('website')->nullable()->after('email');
            $table->string('gstin', 20)->nullable()->after('website');
            $table->text('quotation_terms')->nullable()->after('bank_ifsc');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['company_name', 'logo_path', 'phone', 'mobile', 'email', 'website', 'gstin', 'quotation_terms']);
        });
    }
};
