<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_projects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('code', 40);
            $table->string('name', 190);
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('consultant_contact_id')->nullable();
            $table->string('consultant_name', 190)->nullable();
            $table->unsignedInteger('manager_id')->nullable();
            $table->string('location', 255)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('description')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'code'], 'construction_projects_business_code_unique');
            $table->index(['business_id', 'status'], 'construction_projects_business_status_index');
            $table->index(['business_id', 'customer_id'], 'construction_projects_business_customer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_projects');
    }
};
