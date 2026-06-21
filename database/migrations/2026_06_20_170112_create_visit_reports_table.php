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
        Schema::create('visit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('visit_date');
            $table->string('visit_type');
            $table->string('objective');
            $table->text('report')->nullable();
            $table->date('next_meeting_date')->nullable();
            $table->date('next_call_date')->nullable();
            $table->timestamps();
        });

        Schema::create('visit_report_project', function (Blueprint $table) {
            $table->foreignId('visit_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('visit_report_customer', function (Blueprint $table) {
            $table->foreignId('visit_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('visit_report_contact', function (Blueprint $table) {
            $table->foreignId('visit_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_report_contact');
        Schema::dropIfExists('visit_report_customer');
        Schema::dropIfExists('visit_report_project');
        Schema::dropIfExists('visit_reports');
    }
};
