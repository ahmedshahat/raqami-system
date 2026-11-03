<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// أكواد التكلفة وتوزيع التكلفة التقديرية على البنود اتشالوا من الموديول؛ لو احتجناهم بعدين يتبنوا حسب الاحتياج وقتها.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('construction_boq_cost_allocations');
        Schema::dropIfExists('construction_cost_codes');

        if (Schema::hasColumn('construction_boq_versions', 'estimated_cost_total')) {
            Schema::table('construction_boq_versions', function (Blueprint $table) {
                $table->dropColumn('estimated_cost_total');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('construction_boq_versions', 'estimated_cost_total')) {
            Schema::table('construction_boq_versions', function (Blueprint $table) {
                $table->decimal('estimated_cost_total', 22, 4)->default(0)->after('sales_total');
            });
        }

        if (! Schema::hasTable('construction_cost_codes')) {
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

        if (! Schema::hasTable('construction_boq_cost_allocations')) {
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
    }
};
