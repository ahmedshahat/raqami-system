<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->string('contract_number', 80)->nullable();
            $table->string('contract_type', 30)->default('remeasurement');
            $table->date('signed_at')->nullable();
            $table->decimal('original_value', 22, 4)->default(0);
            $table->decimal('advance_payment_value', 22, 4)->default(0);
            $table->decimal('retention_percent', 8, 4)->default(0);
            $table->decimal('performance_bond_value', 22, 4)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->index(['business_id', 'project_id'], 'construction_contracts_business_project_index');
            $table->index(['business_id', 'status'], 'construction_contracts_business_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_contracts');
    }
};
