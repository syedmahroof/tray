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
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('contact_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
