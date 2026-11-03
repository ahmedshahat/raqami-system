<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_boq_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('version_number');
            $table->string('name', 190);
            $table->enum('status', ['draft', 'approved', 'superseded'])->default('draft');
            $table->decimal('sales_total', 22, 4)->default(0);
            $table->decimal('estimated_cost_total', 22, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->unique(['business_id', 'project_id', 'version_number'], 'construction_boq_versions_unique');
            $table->index(['business_id', 'status'], 'construction_boq_versions_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_boq_versions');
    }
};
