<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_project_structures', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('type', 20)->default('building');
            $table->string('code', 40)->nullable();
            $table->string('name', 190);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'project_id'], 'construction_structures_business_project_index');
            $table->index('parent_id', 'construction_structures_parent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_project_structures');
    }
};
