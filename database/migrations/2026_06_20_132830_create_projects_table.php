<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('builder_id')->constrained();
            $table->foreignId('project_category_id')->constrained();
            $table->string('name');
            $table->string('address')->nullable();
            $table->foreignId('country_id')->nullable()->constrained();
            $table->foreignId('state_id')->nullable()->constrained();
            $table->foreignId('district_id')->nullable()->constrained();
            $table->string('status')->default('planning');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
