<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_subcontracts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('subcontractor_id');
            $table->string('number', 60);
            $table->string('title', 190);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('retention_percent', 8, 4)->default(0);
            $table->decimal('total_value', 22, 4)->default(0);
            $table->enum('status', ['draft', 'approved', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number'], 'construction_subcontracts_number_unique');
            $table->index(['business_id', 'project_id', 'status'], 'construction_subcontracts_project_index');
            $table->index(['business_id', 'subcontractor_id'], 'construction_subcontracts_supplier_index');
        });

        Schema::create('construction_subcontract_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('subcontract_id');
            $table->unsignedInteger('boq_item_id')->nullable();
            $table->string('description', 500);
            $table->enum('calculation_type', ['linear_meter', 'square_meter', 'cubic_meter', 'unit', 'percentage', 'lump_sum']);
            $table->decimal('quantity', 22, 4)->default(1);
            $table->decimal('unit_rate', 22, 4)->default(0);
            $table->decimal('total_value', 22, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['subcontract_id', 'sort_order'], 'construction_subcontract_items_order_index');
            $table->index(['business_id', 'project_id', 'boq_item_id'], 'construction_subcontract_items_project_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_subcontract_items');
        Schema::dropIfExists('construction_subcontracts');
    }
};
