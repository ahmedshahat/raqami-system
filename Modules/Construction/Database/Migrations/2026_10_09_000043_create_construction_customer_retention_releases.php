<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_customer_retention_releases', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('certificate_id');
            $table->string('number', 40);
            $table->date('release_date');
            $table->decimal('amount', 22, 4);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('recorded');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number'], 'construction_customer_retention_number_unique');
            $table->index(['certificate_id', 'status'], 'construction_customer_retention_certificate_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_customer_retention_releases');
    }
};
