<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_measurements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->string('number', 60);
            $table->date('measurement_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->unique(['business_id', 'project_id', 'number'], 'construction_measurements_number_unique');
            $table->index(['business_id', 'status'], 'construction_measurements_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_measurements');
    }
};
