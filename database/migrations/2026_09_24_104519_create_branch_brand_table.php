<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The brands a branch deals in, shown under the footer of its quotations.
     */
    public function up(): void
    {
        Schema::create('branch_brand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_brand');
    }
};
