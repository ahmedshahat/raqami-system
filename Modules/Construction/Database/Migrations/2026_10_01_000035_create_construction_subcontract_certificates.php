<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_subcontract_certificates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('subcontract_id');
            $table->string('number', 60);
            $table->date('certificate_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('gross_value', 22, 4)->default(0);
            $table->decimal('retention_percent', 8, 4)->default(0);
            $table->decimal('retention_value', 22, 4)->default(0);
            $table->decimal('other_deductions', 22, 4)->default(0);
            $table->decimal('net_value', 22, 4)->default(0);
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number'], 'construction_subcontract_certificates_number_unique');
            $table->index(['subcontract_id', 'status'], 'construction_subcontract_certificates_contract_index');
            $table->index(['business_id', 'project_id'], 'construction_subcontract_certificates_project_index');
        });

        Schema::create('construction_subcontract_certificate_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('certificate_id');
            $table->unsignedInteger('subcontract_item_id');
            $table->string('description', 500);
            $table->enum('calculation_type', ['linear_meter', 'square_meter', 'cubic_meter', 'unit', 'percentage', 'lump_sum']);
            $table->decimal('contract_quantity', 22, 4)->default(0);
            $table->decimal('unit_rate', 22, 4)->default(0);
            $table->decimal('previous_quantity', 22, 4)->default(0);
            $table->decimal('current_quantity', 22, 4)->default(0);
            $table->decimal('current_value', 22, 4)->default(0);
            $table->timestamps();

            $table->unique(['certificate_id', 'subcontract_item_id'], 'construction_subcontract_certificate_item_unique');
            $table->index(['subcontract_item_id', 'certificate_id'], 'construction_subcontract_certificate_items_source_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_subcontract_certificate_items');
        Schema::dropIfExists('construction_subcontract_certificates');
    }
};
