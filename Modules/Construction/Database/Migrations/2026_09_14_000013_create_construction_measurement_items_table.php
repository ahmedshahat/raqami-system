<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_measurement_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('measurement_id');
            $table->unsignedInteger('boq_item_id');
            $table->unsignedInteger('structure_id')->nullable();
            $table->unsignedInteger('certificate_id')->nullable();
            $table->decimal('executed_quantity', 22, 4)->default(0);
            $table->decimal('approved_quantity', 22, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['measurement_id', 'boq_item_id', 'structure_id'], 'construction_measurement_item_unique');
            $table->index(['business_id', 'project_id'], 'construction_measurement_items_project_index');
            $table->index(['boq_item_id', 'certificate_id'], 'construction_measurement_items_billing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_measurement_items');
    }
};
