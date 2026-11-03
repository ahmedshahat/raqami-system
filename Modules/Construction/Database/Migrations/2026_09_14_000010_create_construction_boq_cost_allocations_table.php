<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_boq_cost_allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('boq_version_id');
            $table->unsignedInteger('boq_item_id');
            $table->unsignedInteger('cost_code_id');
            $table->decimal('estimated_cost', 22, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['boq_item_id', 'cost_code_id'], 'construction_boq_cost_item_code_unique');
            $table->index(['business_id', 'project_id'], 'construction_boq_cost_business_project_index');
            $table->index('boq_version_id', 'construction_boq_cost_version_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_boq_cost_allocations');
    }
};
