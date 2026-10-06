<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('construction_project_id')->nullable()->after('expense_sub_category_id');
            $table->unsignedInteger('construction_boq_item_id')->nullable()->after('construction_project_id');
            $table->index(['business_id', 'construction_project_id'], 'transactions_business_construction_project_index');
            $table->index('construction_boq_item_id', 'transactions_construction_boq_item_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_business_construction_project_index');
            $table->dropIndex('transactions_construction_boq_item_index');
            $table->dropColumn(['construction_project_id', 'construction_boq_item_id']);
        });
    }
};
