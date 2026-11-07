<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('gst_number')->nullable()->after('email');
        });

        Schema::table('builders', function (Blueprint $table) {
            $table->string('gst_number')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('gst_number');
        });

        Schema::table('builders', function (Blueprint $table) {
            $table->dropColumn('gst_number');
        });
    }
};
