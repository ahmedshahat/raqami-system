<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_customer_certificates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('boq_version_id');
            $table->string('number', 60);
            $table->date('certificate_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->enum('status', ['draft', 'submitted', 'partially_approved', 'approved', 'posted', 'paid', 'cancelled'])->default('draft');
            $table->decimal('previous_gross', 22, 4)->default(0);
            $table->decimal('current_submitted_gross', 22, 4)->default(0);
            $table->decimal('current_approved_gross', 22, 4)->default(0);
            $table->decimal('retention_percent', 8, 4)->default(0);
            $table->decimal('retention_value', 22, 4)->default(0);
            $table->decimal('advance_recovery_value', 22, 4)->default(0);
            $table->decimal('other_deductions_value', 22, 4)->default(0);
            $table->decimal('tax_percent', 8, 4)->default(0);
            $table->decimal('tax_value', 22, 4)->default(0);
            $table->decimal('net_due', 22, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->unique(['business_id', 'project_id', 'number'], 'construction_certificates_number_unique');
            $table->index(['business_id', 'status'], 'construction_certificates_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_customer_certificates');
    }
};
