<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_cost_codes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('code', 60);
            $table->string('name', 190);
            $table->enum('category', ['material', 'labor', 'equipment', 'subcontract', 'overhead']);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'code'], 'construction_cost_codes_business_code_unique');
            $table->index(['business_id', 'category'], 'construction_cost_codes_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_cost_codes');
    }
};
