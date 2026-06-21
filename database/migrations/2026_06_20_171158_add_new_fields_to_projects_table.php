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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('owner_name')->nullable();
            $table->string('owner_phone')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('location')->nullable();
            $table->string('pincode')->nullable();
            $table->date('expected_maturity')->nullable();
            $table->string('preferred_material')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });

        Schema::create('contact_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_project');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
            $table->dropColumn([
                'owner_name',
                'owner_phone',
                'owner_email',
                'location',
                'pincode',
                'expected_maturity',
                'preferred_material',
                'assignee_id',
                'start_date',
                'end_date',
            ]);
        });
    }
};
