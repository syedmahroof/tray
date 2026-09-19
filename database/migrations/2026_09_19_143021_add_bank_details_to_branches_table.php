<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each branch collects into its own account, so the bank details printed on
     * a quotation are kept against the branch that raised it.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('bank_name')->nullable()->after('city');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_branch')->nullable()->after('bank_account_number');
            $table->string('bank_ifsc')->nullable()->after('bank_branch');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_branch', 'bank_ifsc']);
        });
    }
};
