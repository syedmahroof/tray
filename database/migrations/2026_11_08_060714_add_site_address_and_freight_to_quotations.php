<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the goods go, and the freight charged to get them there, which
     * is taxed like the goods.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->text('site_address')->nullable()->after('gstin');
            $table->decimal('freight', 12, 2)->default(0)->after('agent_commission');
            $table->decimal('freight_tax_percent', 5, 2)->default(18)->after('freight');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn(['site_address', 'freight', 'freight_tax_percent']);
        });
    }
};
