<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_subcontract_retention_releases', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('subcontract_id');
            $table->unsignedInteger('certificate_id');
            $table->string('number', 60);
            $table->date('release_date');
            $table->decimal('amount', 22, 4);
            $table->text('notes')->nullable();
            $table->enum('status', ['recorded', 'cancelled'])->default('recorded');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number'], 'construction_subcontract_retention_release_number_unique');
            $table->index(['certificate_id', 'status'], 'construction_subcontract_retention_release_certificate_index');
            $table->index(['business_id', 'subcontract_id'], 'construction_subcontract_retention_release_contract_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_subcontract_retention_releases');
    }
};
