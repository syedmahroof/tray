<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projects no longer track a start or end date.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
    }
};
