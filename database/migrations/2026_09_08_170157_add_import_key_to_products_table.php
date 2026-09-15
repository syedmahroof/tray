<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stable identity for rows that came from the company price list, so a
     * later import of a revised workbook updates the same products instead of
     * duplicating them. Products created by hand leave this null.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('import_key')->nullable()->unique()->after('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['import_key']);
            $table->dropColumn('import_key');
        });
    }
};
