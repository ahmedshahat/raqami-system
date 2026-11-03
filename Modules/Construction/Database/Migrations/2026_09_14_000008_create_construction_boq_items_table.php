<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_boq_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('boq_version_id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->enum('row_type', ['section', 'item'])->default('item');
            $table->string('code', 60);
            $table->string('description', 500);
            $table->string('unit', 40)->nullable();
            $table->decimal('contract_quantity', 22, 4)->default(0);
            $table->decimal('sales_unit_price', 22, 4)->default(0);
            $table->decimal('sales_total', 22, 4)->default(0);
            $table->enum('item_kind', ['standard', 'optional', 'alternative', 'variation'])->default('standard');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['boq_version_id', 'code'], 'construction_boq_items_version_code_unique');
            $table->index(['business_id', 'project_id'], 'construction_boq_items_business_project_index');
            $table->index(['boq_version_id', 'parent_id'], 'construction_boq_items_tree_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_boq_items');
    }
};
