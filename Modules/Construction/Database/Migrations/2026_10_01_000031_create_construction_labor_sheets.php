<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_labor_sheets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->string('number', 60);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('work_site')->nullable();
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number'], 'construction_labor_sheets_number_unique');
            $table->index(['business_id', 'project_id', 'status'], 'construction_labor_sheets_project_index');
        });

        Schema::create('construction_labor_sheet_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('sheet_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('boq_item_id')->nullable();
            $table->string('worker_name', 190);
            $table->string('role_name', 190)->nullable();
            $table->enum('calculation_type', ['hour', 'day', 'lump_sum']);
            $table->decimal('quantity', 22, 4)->default(1);
            $table->decimal('unit_cost', 22, 4)->default(0);
            $table->decimal('total_cost', 22, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sheet_id', 'user_id'], 'construction_labor_lines_sheet_index');
            $table->index(['business_id', 'project_id', 'boq_item_id'], 'construction_labor_lines_project_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_labor_sheet_lines');
        Schema::dropIfExists('construction_labor_sheets');
    }
};
