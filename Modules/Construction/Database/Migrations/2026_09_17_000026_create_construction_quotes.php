<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_quotes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('number', 40);
            $table->date('quote_date');
            $table->unsignedInteger('customer_id');
            $table->string('title', 190);
            $table->unsignedInteger('validity_days')->default(30);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('total', 22, 4)->default(0);
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique(['business_id', 'number'], 'construction_quotes_business_number_unique');
            $table->unique('project_id', 'construction_quotes_project_unique');
            $table->index(['business_id', 'status']);
        });

        Schema::create('construction_quote_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('quote_id');
            $table->string('code', 60);
            $table->string('description', 500);
            $table->unsignedInteger('unit_id');
            $table->string('unit', 40);
            $table->decimal('quantity', 22, 4);
            $table->decimal('unit_price', 22, 4);
            $table->decimal('total', 22, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['quote_id', 'code'], 'construction_quote_items_quote_code_unique');
            $table->index(['business_id', 'quote_id']);
        });

        Schema::table('construction_projects', function (Blueprint $table) {
            $table->unsignedInteger('quote_id')->nullable()->after('customer_id');
            $table->unique('quote_id', 'construction_projects_quote_unique');
        });
    }

    public function down(): void
    {
        Schema::table('construction_projects', function (Blueprint $table) {
            $table->dropUnique('construction_projects_quote_unique');
            $table->dropColumn('quote_id');
        });
        Schema::dropIfExists('construction_quote_items');
        Schema::dropIfExists('construction_quotes');
    }
};
