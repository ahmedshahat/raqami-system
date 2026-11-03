<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_material_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('parent_issue_id')->nullable();
            $table->unsignedInteger('stock_adjustment_transaction_id')->nullable();
            $table->enum('type', ['issue', 'return'])->default('issue');
            $table->string('number', 60);
            $table->date('document_date');
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number'], 'construction_material_documents_number_unique');
            $table->index(['business_id', 'project_id', 'status'], 'construction_material_documents_project_index');
            $table->index('parent_issue_id', 'construction_material_documents_parent_index');
        });

        Schema::create('construction_material_document_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('document_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('variation_id');
            $table->unsignedInteger('boq_item_id')->nullable();
            $table->unsignedInteger('source_issue_line_id')->nullable();
            $table->unsignedInteger('stock_adjustment_line_id')->nullable();
            $table->decimal('quantity', 22, 4);
            $table->decimal('unit_cost', 22, 4)->default(0);
            $table->decimal('total_cost', 22, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'variation_id'], 'construction_material_lines_document_index');
            $table->index(['business_id', 'project_id', 'boq_item_id'], 'construction_material_lines_project_index');
            $table->index('source_issue_line_id', 'construction_material_lines_source_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_material_document_lines');
        Schema::dropIfExists('construction_material_documents');
    }
};
