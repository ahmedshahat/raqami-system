<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_customer_certificate_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('certificate_id');
            $table->unsignedInteger('boq_item_id');
            $table->decimal('previous_quantity', 22, 4)->default(0);
            $table->decimal('submitted_quantity', 22, 4)->default(0);
            $table->decimal('approved_quantity', 22, 4)->default(0);
            $table->decimal('unit_price', 22, 4)->default(0);
            $table->decimal('previous_amount', 22, 4)->default(0);
            $table->decimal('submitted_amount', 22, 4)->default(0);
            $table->decimal('approved_amount', 22, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['certificate_id', 'boq_item_id'], 'construction_certificate_item_unique');
            $table->index(['business_id', 'project_id'], 'construction_certificate_items_project_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_customer_certificate_items');
    }
};
